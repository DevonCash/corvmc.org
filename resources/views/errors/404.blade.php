<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Page Not Found - Corvallis Music Collective</title>
    @include('errors.partials.styles')
</head>
<body>
    <div class="error-container">
        <img src="{{ asset('images/cmc-compact-logo-dark.svg') }}" alt="CMC" class="logo">
        <h1>404</h1>
        <p class="message">We couldn't find that page.</p>
        <a href="/" class="link">Go home</a>
    </div>
</body>
</html>
