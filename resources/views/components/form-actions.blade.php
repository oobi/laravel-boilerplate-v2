{{--
    <x-form-actions>: the action row at the end of a page form. On a long
    form it sticks to the bottom of the screen, so Save stays in reach, until
    the end of the form scrolls into view; on a short form it sits where it
    is. A top edge shows only while it's stuck. Styles: form-actions.css.

    Slots:
    - default: the main action first, then Cancel or Back
    - secondary: other actions on the record (e.g. Delete), kept apart from
      that pair at the far end. On a phone they fold behind a "More actions"
      button, so the row is always one line tall; choosing one closes it

    It sticks within the nearest scrolling ancestor, so nothing between it and
    the page may set overflow hidden/auto (use overflow-clip instead).
--}}
<div
    x-data="{
        stuck: false,
        observer: null,
        init() {
            {{-- It sits 1px past the scroller's edge when stuck, so whatever clips it cuts off its bottom. --}}
            this.observer = new IntersectionObserver(([entry]) => {
                this.stuck = entry.isIntersecting && entry.intersectionRect.bottom < entry.boundingClientRect.bottom - 0.5;
            }, { threshold: 1 });
            this.observer.observe(this.$el);
        },
        destroy() {
            this.observer.disconnect();
        },
    }"
    x-bind:class="{ 'is-stuck': stuck }"
    {{ $attributes->class('ui-form-actions') }}
>
    {{ $slot }}

    @isset($secondary)
        {{-- A disclosure on a phone, like the account menu: opens on click, closes on Escape (focus back to the button), a click outside or Tabbing out.
             Choosing an action hands focus back to the button too, so it isn't lost on the hidden panel (a modal it opens returns focus there). --}}
        <div
            class="ui-form-actions-secondary"
            x-data="{ open: false }"
            x-id="['form-actions-more']"
            x-on:keydown.escape="if (open) { $event.stopPropagation(); open = false; $refs.trigger.focus() }"
            x-on:click.outside="open = false"
            x-on:focusout="if (open && $event.relatedTarget && ! $el.contains($event.relatedTarget)) open = false"
        >
            <x-button.icon
                class="sm:hidden"
                x-ref="trigger"
                :aria-label="__('admin.more_actions')"
                x-bind:aria-controls="$id('form-actions-more')"
                x-bind:aria-expanded="open.toString()"
                aria-expanded="false"
                x-on:click="open = ! open"
            >
                <x-heroicon-o-ellipsis-horizontal class="h-5 w-5" aria-hidden="true" />
            </x-button.icon>

            <div
                class="ui-form-actions-more"
                x-bind:id="$id('form-actions-more')"
                x-bind:class="{ 'is-open': open }"
                x-on:click="if ($event.target.closest('button, a')) { open = false; $nextTick(() => $refs.trigger.offsetParent && $refs.trigger.focus()) }"
            >
                {{ $secondary }}
            </div>
        </div>
    @endisset
</div>
