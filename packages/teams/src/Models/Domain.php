<?php

declare(strict_types=1);

namespace Concise\Teams\Models;

use App\Models\Concerns\Auditable;
use Concise\Teams\Database\Factories\DomainFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * A custom domain that routes to a team — the optional overlay (5h), inert
 * unless config('teams.domains.custom_domains') (over host mode). A domain is
 * `pending` until it proves
 * control via a DNS TXT record carrying its verification token; only a verified
 * domain (verified_at set) ever resolves. See docs/boilerplate/teams-domains.md.
 *
 * @property int $id
 * @property int $team_id
 * @property string $domain
 * @property bool $is_primary
 * @property string $verification_token
 * @property Carbon|null $verified_at
 * @property-read Team $team
 */
class Domain extends Model
{
    /** @use HasFactory<DomainFactory> */
    use Auditable, HasFactory;

    /** Cache key for verifiedHosts(). */
    public const VERIFIED_HOSTS_CACHE_KEY = 'teams:verified-domains';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'domain',
        'is_primary',
    ];

    protected static function booted(): void
    {
        // Every domain gets a token, whatever created it (action, factory, tinker).
        static::creating(function (Domain $domain): void {
            if (empty($domain->verification_token)) {
                $domain->verification_token = Str::lower(Str::random(40));
            }
        });

        // A verified, changed or removed domain is trusted (or not) from the next
        // request. After the commit: another request could otherwise re-cache the
        // old list in between, and a removed domain would stay trusted for a day.
        $forgetVerifiedHosts = function (): void {
            DB::afterCommit(fn (): bool => Cache::forget(self::VERIFIED_HOSTS_CACHE_KEY));
        };

        static::saved($forgetVerifiedHosts);
        static::deleted($forgetVerifiedHosts);
    }

    /**
     * Every verified domain: the teams tier's custom-domain entries in
     * App\Support\TrustedHosts, read on every request. Cleared whenever a
     * domain changes; the daily expiry only backstops a request that stored
     * the old list just as a domain was verified.
     *
     * @return list<string>
     */
    public static function verifiedHosts(): array
    {
        return Cache::remember(self::VERIFIED_HOSTS_CACHE_KEY, now()->addDay(), fn (): array => static::query()
            ->whereNotNull('verified_at')
            ->pluck('domain')
            ->all());
    }

    protected function casts(): array
    {
        return [
            'is_primary' => 'boolean',
            'verified_at' => 'datetime',
        ];
    }

    protected static function newFactory(): DomainFactory
    {
        return DomainFactory::new();
    }

    /**
     * Domains are stored canonical (lowercase, no scheme/path/trailing dot) so
     * host matching and the unique index compare like for like — mirrors the
     * User::email normalisation pattern (.ai/rules/app.md).
     */
    protected function domain(): Attribute
    {
        return Attribute::set(fn (?string $value): ?string => self::normalize($value));
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function isVerified(): bool
    {
        return $this->verified_at !== null;
    }

    public function markVerified(): void
    {
        $this->forceFill(['verified_at' => now()])->save();
    }

    /** The DNS host that must carry the TXT verification record (D-DOM-3). */
    public function verificationHost(): string
    {
        return '_bp-verify.'.$this->domain;
    }

    /** The exact TXT value that proves control of the domain. */
    public function expectedTxtValue(): string
    {
        return 'bp-verify='.$this->verification_token;
    }

    /**
     * Canonical form of a user-entered domain, for storage and for validating
     * uniqueness/reservation against the value that will actually be stored.
     */
    public static function normalize(?string $domain): ?string
    {
        if ($domain === null) {
            return null;
        }

        $domain = Str::lower(trim($domain));
        $domain = (string) preg_replace('#^https?://#', '', $domain); // strip scheme
        $domain = explode('/', $domain)[0];                           // strip path

        return trim($domain, '.');
    }
}
