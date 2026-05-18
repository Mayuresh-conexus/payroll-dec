<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Access Restricted</title>
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
            max-width: 460px;
            width: 90%;
            padding: 48px;
            border-radius: 20px;
            background: rgba(255, 255, 255, 0.04);
            backdrop-filter: blur(20px);
            box-shadow: 0 40px 120px rgba(0, 0, 0, .5);
            text-align: center;
            animation: float 6s ease-in-out infinite;
        }

        @keyframes float {

            0%,
            100% {
                transform: translateY(0px);
            }

            50% {
                transform: translateY(-8px);
            }
        }

        .icon {
            font-size: 64px;
            margin-bottom: 16px;
            animation: shake 2.8s ease-in-out infinite;
        }

        @keyframes shake {

            0%,
            100% {
                transform: rotate(0deg);
            }

            25% {
                transform: rotate(-6deg);
            }

            75% {
                transform: rotate(6deg);
            }
        }

        h1 {
            font-size: 28px;
            margin-bottom: 12px;
        }

        p {
            color: #cbd5f5;
            line-height: 1.6;
            margin-bottom: 32px;
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
        <div class="icon">🛑</div>
        <h1>Access Restricted</h1>
        <p>
            This section isn’t available for your role.<br>
            Please contact an administrator if you believe this is a mistake.
        </p>

        <div class="actions">
            <button class="btn btn-primary" onclick="window.location='{{ route('dashboard') }}'">
                Go to Dashboard
            </button>
            <button class="btn btn-ghost" onclick="history.back()">
                Go Back
            </button>
        </div>
    </div>

</body>

</html>
