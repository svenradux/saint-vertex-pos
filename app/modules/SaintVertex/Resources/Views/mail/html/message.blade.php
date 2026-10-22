{{-- Saint Vertex presentation modifications: Copyright © 2026 Saint Vertex.
     Uses the existing Laravel mail components; their ownership and licenses are unchanged. --}}
<x-mail::layout>
    <x-slot:header>
        <x-mail::header :url="config('app.url')">Vertex POS</x-mail::header>
    </x-slot:header>

    {!! $slot !!}

    @isset($subcopy)
        <x-slot:subcopy>
            <x-mail::subcopy>{!! $subcopy !!}</x-mail::subcopy>
        </x-slot:subcopy>
    @endisset

    <x-slot:footer>
        <x-mail::footer>© 2026 Saint Vertex</x-mail::footer>
    </x-slot:footer>
</x-mail::layout>
