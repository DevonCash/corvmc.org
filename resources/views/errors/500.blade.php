<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Server Error - Corvallis Music Collective</title>
    @include('errors.partials.styles')
</head>
<body>
    <div class="error-container">
        <img src="{{ asset('images/cmc-compact-logo-dark.svg') }}" alt="CMC" class="logo">
        <h1>500</h1>
        <p class="message">Something went wrong on our end. Our team has been notified.</p>
        <a href="/" class="link">Go home</a>
    </div>
</body>
</html>
