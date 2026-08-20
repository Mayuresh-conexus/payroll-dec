<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Slow Down</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <style>
        body {
            margin: 0;
            height: 100vh;
            font-family: 'Inter', system-ui, sans-serif;
            background: #0f172a;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
        }

        .card {
            max-width: 480px;
            width: 90%;
            padding: 48px;
            border-radius: 20px;
            background: rgba(255, 255, 255, 0.04);
            backdrop-filter: blur(20px);
            box-shadow: 0 40px 120px rgba(0, 0, 0, .5);
            text-align: center;
        }

        .icon {
            font-size: 64px;
            margin-bottom: 16px;
        }

        h1 {
            font-size: 28px;
            margin-bottom: 12px;
        }

        p {
            color: #cbd5f5;
            line-height: 1.6;
            margin-bottom: 12px;
        }

        .note {
            color: #94a3b8;
            font-size: 13px;
            line-height: 1.6;
            margin-bottom: 32px;
        }

        .countdown {
            font-weight: 700;
            color: #fff;
        }

        .actions {
            display: flex;
            gap: 12px;
            justify-content: center;
        }

        .btn {
            padding: 12px 20px;
            border-radius: 12px;
            border: none;
            cursor: pointer;
            font-weight: 600;
            transition: all .3s ease;
        }

        .btn-primary {
            background: #9e2a2b;
            color: #fff;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 30px rgba(158, 42, 43, .5);
        }

        .btn-ghost {
            background: transparent;
            color: #c7d2fe;
            border: 1px solid rgba(255, 255, 255, .1);
        }

        .btn-ghost:hover {
            background: rgba(255, 255, 255, .06);
        }
    </style>
</head>

<body>

    <div class="card">
        <div class="icon">⏳</div>
        <h1>Too Many Attempts</h1>
        <p>
            This action is rate-limited, and you have used the attempts allowed for this minute.
        </p>
        {{-- The reassurance is the point of this page: someone who has just tried
             to restore a database needs to know nothing happened to it. --}}
        <p class="note">
            Nothing was carried out — the request was turned away before it ran, so your data is untouched.
            Failed attempts count too, which is usually how this limit gets reached.
            <br><br>
            You can try again in <span class="countdown" id="countdown">{{ $exception?->getHeaders()['Retry-After'] ?? 60 }}</span> seconds.
        </p>

        <div class="actions">
            <button class="btn btn-primary" onclick="history.back()">
                Go Back
            </button>
            <button class="btn btn-ghost" onclick="window.location='{{ route('dashboard') }}'">
                Go to Dashboard
            </button>
        </div>
    </div>

    <script>
        // Counts down rather than asking the admin to guess when the minute is up.
        const el = document.getElementById('countdown');
        let left = parseInt(el.textContent, 10) || 60;

        const tick = setInterval(() => {
            left -= 1;
            el.textContent = Math.max(0, left);

            if (left <= 0) {
                clearInterval(tick);
            }
        }, 1000);
    </script>

</body>

</html>
