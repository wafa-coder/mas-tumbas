<?php

/**
 * Script untuk generate password hash
 * Jalankan file ini di browser untuk membuat hash password baru
 */

// Ubah password di bawah ini sesuai keinginan Anda
$password = 'password123';

// Generate hash menggunakan bcrypt
$hash = password_hash($password, PASSWORD_DEFAULT);

?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Password Hash Generator</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>

<body class="bg-gray-100 flex items-center justify-center min-h-screen">
    <div class="w-full max-w-2xl p-8 bg-white rounded-lg shadow-md">
        <h1 class="text-2xl font-bold text-center text-gray-800 mb-6">Password Hash Generator</h1>

        <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-6">
            <p class="text-sm text-gray-700 mb-2">
                <strong>Password yang di-hash:</strong>
                <code class="bg-gray-200 px-2 py-1 rounded"><?php echo htmlspecialchars($password); ?></code>
            </p>
            <p class="text-sm text-gray-700">
                <strong>Hash Result:</strong>
            </p>
            <div class="bg-white p-3 rounded border border-gray-300 mt-2 break-all font-mono text-xs">
                <?php echo $hash; ?>
            </div>
        </div>

        <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4 mb-6">
            <h2 class="font-bold text-gray-800 mb-2">📝 Cara Menggunakan:</h2>
            <ol class="list-decimal list-inside text-sm text-gray-700 space-y-1">
                <li>Ubah variabel <code class="bg-gray-200 px-1 rounded">$password</code> di file ini dengan password yang Anda inginkan</li>
                <li>Refresh halaman ini di browser</li>
                <li>Copy hash yang dihasilkan</li>
                <li>Paste ke <code class="bg-gray-200 px-1 rounded">ADMIN_PASSWORD_HASH</code> di file <strong>admin.php</strong></li>
                <li><strong class="text-red-600">HAPUS file ini setelah selesai!</strong></li>
            </ol>
        </div>

        <div class="bg-green-50 border border-green-200 rounded-lg p-4">
            <h2 class="font-bold text-gray-800 mb-2">✅ Contoh Penggunaan di admin.php:</h2>
            <pre class="bg-white p-3 rounded border border-gray-300 text-xs overflow-x-auto"><code>define('ADMIN_USERNAME', 'admin');
define('ADMIN_PASSWORD_HASH', '<?php echo $hash; ?>');
define('UPLOAD_DIR', 'uploads/');</code></pre>
        </div>

        <div class="mt-6 text-center">
            <form method="POST" onsubmit="return confirm('Yakin ingin generate hash baru?')">
                <input type="text" name="new_password" placeholder="Masukkan password baru"
                    class="shadow border rounded py-2 px-3 text-gray-700 w-64 mr-2">
                <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
                    Generate Hash Baru
                </button>
            </form>
        </div>

        <?php if (isset($_POST['new_password']) && !empty($_POST['new_password'])):
            $new_hash = password_hash($_POST['new_password'], PASSWORD_DEFAULT);
        ?>
            <div class="mt-4 bg-purple-50 border border-purple-200 rounded-lg p-4">
                <p class="text-sm text-gray-700 mb-2">
                    <strong>Password baru:</strong>
                    <code class="bg-gray-200 px-2 py-1 rounded"><?php echo htmlspecialchars($_POST['new_password']); ?></code>
                </p>
                <p class="text-sm text-gray-700">
                    <strong>Hash baru:</strong>
                </p>
                <div class="bg-white p-3 rounded border border-gray-300 mt-2 break-all font-mono text-xs">
                    <?php echo $new_hash; ?>
                </div>
            </div>
        <?php endif; ?>

        <div class="mt-6 p-4 bg-red-50 border border-red-200 rounded-lg">
            <p class="text-sm text-red-700 font-bold">⚠️ PENTING:</p>
            <p class="text-sm text-red-700">Hapus file <strong>generate_password.php</strong> ini setelah Anda selesai generate password hash untuk keamanan!</p>
        </div>
    </div>
</body>

</html>