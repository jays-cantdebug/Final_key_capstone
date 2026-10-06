{{--
    Preload the Figtree weights a layout shows at first paint, so the browser
    starts fetching them with the HTML instead of only after it has parsed
    app.css. Vite::asset() gives the exact hashed URL app.css's @font-face
    rules use, and `crossorigin` matches the CORS mode fonts are always
    fetched in — without either, the preload wouldn't be reused and each
    font would download twice. font-display stays `swap` (resources/css/app.css).

    $weights: the weights to preload, e.g. [400, 500, 600].
--}}
@foreach ($weights as $weight)
    <link rel="preload" href="{{ Vite::asset("resources/fonts/figtree-{$weight}.woff2") }}" as="font" type="font/woff2" crossorigin>
@endforeach
