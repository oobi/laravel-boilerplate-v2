{{--
    <x-skip-link />: the first thing in a page's body: hidden until focused, it lets a keyboard
    user jump past the header and navigation to the page's <main id="main-content">.
--}}
<a href="#main-content"
    class="sr-only focus:not-sr-only focus:fixed focus:start-2 focus:top-2 focus:z-[100] focus:rounded-box focus:bg-base-100 focus:px-4 focus:py-2 focus:text-base-content focus:shadow-lg focus:outline-2 focus:outline-primary">
    {{ __('Skip to content') }}
</a>
