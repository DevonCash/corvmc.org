<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Forbidden - Corvallis Music Collective</title>
    @include('errors.partials.styles')
</head>
<body>
    <div class="error-container">
        <img src="{{ asset('images/cmc-compact-logo-dark.svg') }}" alt="CMC" class="logo">
        <h1>403</h1>
        <p class="message">You don't have permission to access this page.</p>
        <a href="/" class="link">Go home</a>
    </div>
</body>
</html>
