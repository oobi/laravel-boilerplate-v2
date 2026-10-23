{{--
    <x-form-actions>: the action row at the end of a page form. On a long
    form it sticks to the bottom of the screen, so Save stays in reach, until
    the end of the form scrolls into view; on a short form it sits where it
    is. A top edge shows only while it's stuck. Styles: form-actions.css.

    Slots:
    - default: the main action first, then Cancel or Back
    - secondary: another action on the record (e.g. Delete), kept apart from
      that pair: the far end on sm and up, its own row under a divider on a
      phone

    It sticks within the nearest scrolling ancestor, so nothing between it and
    the page may set overflow hidden/auto (use overflow-clip instead).
--}}
<div
    x-data="{
        stuck: false,
        init() {
            {{-- It sits 1px past the bottom edge when stuck, so it's no longer fully visible there. --}}
            this.observer = new IntersectionObserver(([entry]) => {
                this.stuck = entry.intersectionRatio < 1 && entry.boundingClientRect.bottom > entry.rootBounds.bottom;
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
        <div class="ui-form-actions-secondary">
            {{ $secondary }}
        </div>
    @endisset
</div>
