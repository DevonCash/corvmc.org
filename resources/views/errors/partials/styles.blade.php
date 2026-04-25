<style>
    @import url('https://fonts.googleapis.com/css2?family=Lexend:wght@300;500;700&display=swap');

    * { margin: 0; padding: 0; box-sizing: border-box; }

    body {
        font-family: 'Lexend', system-ui, sans-serif;
        background: oklch(0.15 0.005 15);
        color: oklch(0.94 0.015 60);
        min-height: 100vh;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .error-container {
        text-align: center;
        padding: 2rem;
    }

    .logo {
        height: 3rem;
        margin-bottom: 2rem;
    }

    h1 {
        font-size: 3rem;
        font-weight: 700;
        margin-bottom: 0.5rem;
        color: oklch(0.98 0.01 65);
    }

    .message {
        font-size: 1.1rem;
        font-weight: 300;
        color: oklch(0.68 0.03 45);
        margin-bottom: 1.5rem;
    }

    .link {
        display: inline-block;
        font-size: 0.9rem;
        font-weight: 500;
        color: oklch(0.67 0.18 43);
        text-decoration: none;
        border-bottom: 1px solid transparent;
        transition: border-color 0.2s;
    }

    .link:hover {
        border-bottom-color: oklch(0.67 0.18 43);
    }
</style>
