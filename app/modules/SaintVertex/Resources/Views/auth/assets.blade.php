{{-- Native auth mounts eagerly; wait for its translation dictionary before importing it. --}}
<script type="module">
if (!window.nsLanguage.getEntries('NexoPOS')) {
    await new Promise(resolve => window.nsLanguage.onReadyCallback(resolve));
}
await import(@json(app('Illuminate\Foundation\Vite')->asset('resources/ts/auth.ts')));
</script>
{{-- Copyright © 2026 Saint Vertex. Original native-auth asset integration. --}}
