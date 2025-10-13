<?php

session_save_path('/tmp');  // wajib di serverless Vercel
session_start();

// Enable error reporting untuk debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/php_errors.log');

require __DIR__ . '/config.php';
require __DIR__ . '/../vendor/autoload.php';


use GuzzleHttp\Client as GuzzleClient;

define('ADMIN_USERNAME', 'admin');
define('ADMIN_PASSWORD_HASH', '$2y$10$uGafHJyeTOp..exjodzqz.jDdaNb0EmH9zBqvebxq3B.yMPFh4X6.');

// Supabase Helper
class SupabaseHelper
{
    private $url, $key, $client;
    public function __construct($url, $key)
    {
        $this->url = $url;
        $this->key = $key;
        $this->client = new GuzzleClient([
            'base_uri' => $url . '/rest/v1/',
            'headers' => ['apikey' => $key, 'Authorization' => 'Bearer ' . $key, 'Content-Type' => 'application/json', 'Prefer' => 'return=representation']
        ]);
    }
    public function from($table)
    {
        return new SupabaseQuery($this->client, $table);
    }
}

class SupabaseQuery
{
    private $client, $table, $filters = [], $selectFields = '*', $orderBy = '', $method = 'GET', $data = null;

    public function __construct($client, $table)
    {
        $this->client = $client;
        $this->table = $table;
    }
    public function select($fields = '*')
    {
        $this->selectFields = $fields;
        $this->method = 'GET';
        return $this;
    }
    public function eq($column, $value)
    {
        $this->filters[] = "$column=eq.$value";
        return $this;
    }
    public function order($column, $direction = 'asc')
    {
        $this->orderBy = "$column.$direction";
        return $this;
    }
    public function insert($data)
    {
        $this->method = 'POST';
        $this->data = $data;
        return $this;
    }
    public function update($data)
    {
        $this->method = 'PATCH';
        $this->data = $data;
        return $this;
    }
    public function delete()
    {
        $this->method = 'DELETE';
        return $this;
    }

    public function execute()
    {
        $query = '';
        if ($this->method === 'GET') {
            $query = '?select=' . $this->selectFields;
            if (!empty($this->filters)) $query .= '&' . implode('&', $this->filters);
            if (!empty($this->orderBy)) $query .= '&order=' . $this->orderBy;
        } else {
            if (!empty($this->filters)) $query = '?' . implode('&', $this->filters);
        }

        try {
            switch ($this->method) {
                case 'GET':
                    $response = $this->client->get($this->table . $query);
                    break;
                case 'POST':
                    $response = $this->client->post($this->table . $query, ['json' => $this->data]);
                    break;
                case 'PATCH':
                    $response = $this->client->patch($this->table . $query, ['json' => $this->data]);
                    break;
                case 'DELETE':
                    $response = $this->client->delete($this->table . $query);
                    break;
                default:
                    throw new Exception("Unsupported method");
            }
            $data = json_decode($response->getBody()->getContents());
            return (object)['data' => $data, 'status' => $response->getStatusCode()];
        } catch (Exception $e) {
            return (object)['data' => [], 'error' => $e->getMessage(), 'status' => 0];
        }
    }
}

$client = new SupabaseHelper($supabaseUrl, $supabaseKey);

// LOGIN
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    if ($_POST['username'] === ADMIN_USERNAME && password_verify($_POST['password'], ADMIN_PASSWORD_HASH)) {
        $_SESSION['admin_logged_in'] = true;
        header('Location: admin');
        exit;
    }
    $pesan = '<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">Username atau password salah!</div>';
}

if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: admin');
    exit;
}

if (!isset($_SESSION['admin_logged_in'])) {
?>
    <!DOCTYPE html>
    <html lang="id">

    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Admin Login</title>
        <script src="https://cdn.tailwindcss.com"></script>
    </head>

    <body class="bg-gray-100 flex items-center justify-center min-h-screen p-4">
        <div class="w-full max-w-md p-6 bg-white rounded-lg shadow-md">
            <h1 class="text-2xl font-bold text-center mb-6">Admin Login</h1>
            <?php echo $pesan ?? ''; ?>
            <form method="POST">
                <input type="hidden" name="login" value="1">
                <div class="mb-4">
                    <label class="block text-gray-700 text-sm font-bold mb-2">Username</label>
                    <input type="text" name="username" class="shadow border rounded w-full py-2 px-3" required>
                </div>
                <div class="mb-6">
                    <label class="block text-gray-700 text-sm font-bold mb-2">Password</label>
                    <input type="password" name="password" class="shadow border rounded w-full py-2 px-3" required>
                </div>
                <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">Login</button>
            </form>
        </div>
    </body>

    </html>
<?php exit;
}

// CRUD
$action = $_POST['action'] ?? 'list';
$tabel = $_POST['tabel'] ?? '';

// HAPUS
if ($action === 'hapus' && isset($_POST['id']) && $tabel) {
    try {
        $res = $client->from($tabel)->select('*')->eq('id', $_POST['id'])->execute();
        if (!empty($res->data)) {
            $data = (array)$res->data[0];
            // Hapus file dari Supabase Storage
            if ($tabel === 'layanan' && !empty($data['icon'])) {
                deleteFromSupabase($data['icon'], $supabaseStorageUrl, $supabaseKey, $supabaseStorageBucket);
            }
            if ($tabel === 'galeri' && !empty($data['gambar'])) {
                deleteFromSupabase($data['gambar'], $supabaseStorageUrl, $supabaseKey, $supabaseStorageBucket);
            }
        }
        $client->from($tabel)->delete()->eq('id', $_POST['id'])->execute();
        $_SESSION['pesan'] = 'dihapus';
        header("Location: admin");
    } catch (Exception $e) {
        $_SESSION['pesan_error'] = $e->getMessage();
        header("Location: admin");
    }
    exit;
}

// TAMBAH / EDIT
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['simpan'])) {
    try {
        $tabel = $_POST['tabel'];
        $id = $_POST['id'] ?? null;
        $data = $_POST;
        unset($data['simpan'], $data['tabel'], $data['id'], $data['icon_lama'], $data['gambar_lama']);

        // Debug log
        error_log("=== START SIMPAN DATA ===");
        error_log("Tabel: " . $tabel);
        error_log("ID: " . ($id ?? 'NULL (insert baru)'));
        error_log("POST data: " . print_r($_POST, true));
        error_log("FILES data: " . print_r($_FILES, true));

        if ($tabel === 'layanan') {
            // Cek apakah ada file yang diupload
            if (isset($_FILES['icon']) && $_FILES['icon']['error'] !== UPLOAD_ERR_NO_FILE) {
                error_log("Mencoba upload icon...");
                $newIconUrl = uploadToSupabase($_FILES['icon'], $supabaseStorageUrl, $supabaseKey, $supabaseStorageBucket);

                if ($newIconUrl) {
                    error_log("Upload icon berhasil: " . $newIconUrl);
                    // Hapus icon lama jika ada (saat edit)
                    if ($id && !empty($_POST['icon_lama'])) {
                        error_log("Menghapus icon lama: " . $_POST['icon_lama']);
                        deleteFromSupabase($_POST['icon_lama'], $supabaseStorageUrl, $supabaseKey, $supabaseStorageBucket);
                    }
                    $data['icon'] = $newIconUrl;
                } else {
                    error_log("Upload icon GAGAL!");
                    // Jika upload gagal dan ini edit, pertahankan icon lama
                    if ($id && !empty($_POST['icon_lama'])) {
                        $data['icon'] = $_POST['icon_lama'];
                        error_log("Mempertahankan icon lama karena upload gagal");
                    }
                }
            } else {
                // Tidak ada file baru diupload
                if ($id && !empty($_POST['icon_lama'])) {
                    // Edit tanpa ganti icon
                    $data['icon'] = $_POST['icon_lama'];
                    error_log("Edit tanpa upload icon baru, gunakan icon lama");
                } else {
                    // Insert tanpa icon
                    error_log("Insert tanpa icon");
                }
            }
        }

        if ($tabel === 'galeri') {
            // Cek apakah ada file yang diupload
            if (isset($_FILES['gambar']) && $_FILES['gambar']['error'] !== UPLOAD_ERR_NO_FILE) {
                error_log("Mencoba upload gambar...");
                $newImageUrl = uploadToSupabase($_FILES['gambar'], $supabaseStorageUrl, $supabaseKey, $supabaseStorageBucket);

                if ($newImageUrl) {
                    error_log("Upload gambar berhasil: " . $newImageUrl);
                    // Hapus gambar lama jika ada (saat edit)
                    if ($id && !empty($_POST['gambar_lama'])) {
                        error_log("Menghapus gambar lama: " . $_POST['gambar_lama']);
                        deleteFromSupabase($_POST['gambar_lama'], $supabaseStorageUrl, $supabaseKey, $supabaseStorageBucket);
                    }
                    $data['gambar'] = $newImageUrl;
                } else {
                    error_log("Upload gambar GAGAL!");
                    // Jika upload gagal dan ini edit, pertahankan gambar lama
                    if ($id && !empty($_POST['gambar_lama'])) {
                        $data['gambar'] = $_POST['gambar_lama'];
                        error_log("Mempertahankan gambar lama karena upload gagal");
                    }
                }
            } else {
                // Tidak ada file baru diupload
                if ($id && !empty($_POST['gambar_lama'])) {
                    // Edit tanpa ganti gambar
                    $data['gambar'] = $_POST['gambar_lama'];
                    error_log("Edit tanpa upload gambar baru, gunakan gambar lama");
                } else {
                    // Insert tanpa gambar
                    error_log("Insert tanpa gambar");
                }
            }
        }

        error_log("Data yang akan disimpan: " . print_r($data, true));

        // Simpan ke database
        if ($id) {
            error_log("Melakukan UPDATE ke database...");
            $result = $client->from($tabel)->eq('id', $id)->update($data)->execute();
            error_log("Result UPDATE: " . print_r($result, true));
        } else {
            error_log("Melakukan INSERT ke database...");
            $result = $client->from($tabel)->insert([$data])->execute();
            error_log("Result INSERT: " . print_r($result, true));
        }

        $_SESSION['pesan'] = $id ? 'diperbarui' : 'ditambah';
        error_log("=== SIMPAN DATA BERHASIL ===");
        header("Location: admin");
    } catch (Exception $e) {
        $_SESSION['pesan_error'] = $e->getMessage();
        error_log("ERROR SIMPAN: " . $e->getMessage());
        error_log("=== SIMPAN DATA GAGAL ===");
        header("Location: admin");
    }
    exit;
}

// Ambil data
$layanan = $client->from('layanan')->select('*')->order('id')->execute()->data ?? [];
$galeri = $client->from('galeri')->select('*')->order('id')->execute()->data ?? [];

// Ambil pesan dari session
$pesan_success = $_SESSION['pesan'] ?? '';
$pesan_error = $_SESSION['pesan_error'] ?? '';
unset($_SESSION['pesan'], $_SESSION['pesan_error']);
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Panel</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>

<body class="bg-gray-100">
    <header class="bg-white shadow sticky top-0 z-10">
        <div class="container mx-auto px-4 py-3 flex flex-wrap gap-2 justify-between items-center">
            <h1 class="text-lg md:text-xl font-bold text-blue-600"><i class="fas fa-cog mr-2"></i>Admin Panel</h1>
            <div class="flex gap-2">
                <a href="index.php" class="bg-blue-500 hover:bg-blue-600 text-white font-bold py-2 px-3 rounded text-sm">
                    <i class="fas fa-home"></i><span class="hidden sm:inline ml-2">Website</span>
                </a>
                <a href="?logout=1" class="bg-red-500 hover:bg-red-600 text-white font-bold py-2 px-3 rounded text-sm">
                    <i class="fas fa-sign-out-alt"></i><span class="hidden sm:inline ml-2">Logout</span>
                </a>
            </div>
        </div>
    </header>

    <main class="container mx-auto px-4 py-4 md:py-8">
        <?php if ($pesan_error): ?>
            <div class="bg-red-100 border-red-400 text-red-700 border px-4 py-3 rounded mb-4">
                Error: <?php echo htmlspecialchars($pesan_error); ?>
            </div>
        <?php endif; ?>

        <?php if ($pesan_success): ?>
            <div class="bg-green-100 border-green-400 text-green-700 border px-4 py-3 rounded mb-4">
                Data berhasil <?php echo htmlspecialchars($pesan_success); ?>!
            </div>
        <?php endif; ?>

        <?php if ($action === 'tambah' || $action === 'edit'):
            $data_edit = null;
            if ($action === 'edit' && isset($_POST['id'])) {
                $res = $client->from($tabel)->select('*')->eq('id', $_POST['id'])->execute();
                if (!empty($res->data)) $data_edit = (array)$res->data[0];
            }
            $fields = ($tabel === 'layanan') ? ['judul' => 'text', 'deskripsi' => 'text', 'icon' => 'file'] : ['caption' => 'text', 'gambar' => 'file'];
        ?>
            <div class="bg-white p-4 md:p-6 rounded-lg shadow">
                <h2 class="text-xl md:text-2xl font-bold mb-4 capitalize"><?php echo $action . ' ' . $tabel; ?></h2>
                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="simpan" value="1">
                    <input type="hidden" name="tabel" value="<?php echo $tabel; ?>">
                    <?php if ($data_edit): ?><input type="hidden" name="id" value="<?php echo $data_edit['id']; ?>"><?php endif; ?>

                    <?php foreach ($fields as $name => $type): ?>
                        <div class="mb-4">
                            <label class="block text-gray-700 text-sm font-bold mb-2 capitalize">
                                <?php echo str_replace('_', ' ', $name); ?>
                                <?php if ($type === 'file'): ?><span class="text-xs text-gray-500 font-normal">(Max 5MB, JPG/PNG/GIF)</span><?php endif; ?>
                            </label>

                            <?php if ($type === 'file'): ?>
                                <input type="file" name="<?php echo $name; ?>" accept="image/jpeg,image/jpg,image/png,image/gif,image/webp" class="w-full text-sm border rounded py-2 px-3">
                                <?php if ($data_edit && !empty($data_edit[$name])): ?>
                                    <div class="mt-2">
                                        <p class="text-xs text-gray-500 mb-1">Gambar saat ini:</p>
                                        <img src="<?php echo htmlspecialchars($data_edit[$name]); ?>" class="h-20 w-20 object-cover rounded border" onerror="this.src='data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 width=%22100%22 height=%22100%22%3E%3Crect fill=%22%23ddd%22 width=%22100%22 height=%22100%22/%3E%3Ctext x=%2250%25%22 y=%2250%25%22 text-anchor=%22middle%22 dy=%22.3em%22 fill=%22%23999%22%3ENo Image%3C/text%3E%3C/svg%3E';">
                                        <input type="hidden" name="<?php echo $name; ?>_lama" value="<?php echo htmlspecialchars($data_edit[$name]); ?>">
                                        <p class="text-xs text-gray-400 mt-1">Kosongkan jika tidak ingin mengubah gambar</p>
                                    </div>
                                <?php endif; ?>
                            <?php else: ?>
                                <input type="<?php echo $type; ?>" name="<?php echo $name; ?>" value="<?php echo htmlspecialchars($data_edit[$name] ?? ''); ?>" class="shadow border rounded w-full py-2 px-3" required>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>

                    <div class="flex flex-col sm:flex-row gap-2 justify-end mt-6">
                        <button type="button" onclick="history.back()" class="text-center bg-gray-200 hover:bg-gray-300 text-gray-800 font-bold py-2 px-6 rounded">Batal</button>
                        <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-6 rounded">
                            <i class="fas fa-save mr-2"></i>Simpan
                        </button>
                    </div>
                </form>
            </div>
        <?php else: ?>
            <!-- Kelola Layanan -->
            <div class="bg-white p-4 md:p-6 rounded-lg shadow mb-6">
                <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3 mb-4">
                    <h2 class="text-xl md:text-2xl font-bold"><i class="fas fa-list mr-2 text-blue-600"></i>Kelola Layanan</h2>
                    <form method="POST" class="w-full sm:w-auto">
                        <input type="hidden" name="action" value="tambah">
                        <input type="hidden" name="tabel" value="layanan">
                        <button type="submit" class="w-full sm:w-auto bg-green-500 hover:bg-green-600 text-white font-bold py-2 px-4 rounded">
                            <i class="fas fa-plus mr-2"></i>Tambah
                        </button>
                    </form>
                </div>

                <!-- Mobile Cards -->
                <div class="block md:hidden space-y-3">
                    <?php foreach ($layanan as $item): $item = (array)$item; ?>
                        <div class="border rounded-lg p-4">
                            <div class="flex gap-3">
                                <div class="flex-shrink-0">
                                    <?php if (!empty($item['icon'])): ?>
                                        <img src="<?php echo htmlspecialchars($item['icon']); ?>" class="h-12 w-12 object-cover rounded-full border-2 border-blue-500" onerror="this.src='data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 width=%2250%22 height=%2250%22%3E%3Ccircle fill=%22%23e5e7eb%22 cx=%2225%22 cy=%2225%22 r=%2225%22/%3E%3Ctext x=%2250%25%22 y=%2250%25%22 text-anchor=%22middle%22 dy=%22.3em%22 fill=%22%239ca3af%22 font-size=%2220%22%3E📦%3C/text%3E%3C/svg%3E';">
                                    <?php else: ?>
                                        <div class="h-12 w-12 bg-gray-200 rounded-full flex items-center justify-center">
                                            <i class="fas fa-image text-gray-400"></i>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <h3 class="font-bold truncate"><?php echo htmlspecialchars($item['judul'] ?? ''); ?></h3>
                                    <p class="text-sm text-gray-600 line-clamp-2"><?php echo htmlspecialchars($item['deskripsi'] ?? ''); ?></p>
                                    <div class="flex gap-2 mt-2">
                                        <form method="POST" class="flex-1">
                                            <input type="hidden" name="action" value="edit">
                                            <input type="hidden" name="tabel" value="layanan">
                                            <input type="hidden" name="id" value="<?php echo $item['id']; ?>">
                                            <button type="submit" class="w-full text-blue-500 hover:text-blue-700 text-sm font-semibold">
                                                <i class="fas fa-edit"></i> Edit
                                            </button>
                                        </form>
                                        <form method="POST" class="flex-1" onsubmit="return confirm('Yakin hapus?')">
                                            <input type="hidden" name="action" value="hapus">
                                            <input type="hidden" name="tabel" value="layanan">
                                            <input type="hidden" name="id" value="<?php echo $item['id']; ?>">
                                            <button type="submit" class="w-full text-red-500 hover:text-red-700 text-sm font-semibold">
                                                <i class="fas fa-trash"></i> Hapus
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Desktop Table -->
                <div class="hidden md:block overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-gray-200">
                            <tr>
                                <th class="py-2 px-4 text-left">Icon</th>
                                <th class="py-2 px-4 text-left">Judul</th>
                                <th class="py-2 px-4 text-left">Deskripsi</th>
                                <th class="py-2 px-4 text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($layanan as $item): $item = (array)$item; ?>
                                <tr class="border-b hover:bg-gray-50">
                                    <td class="py-2 px-4">
                                        <?php if (!empty($item['icon'])): ?>
                                            <img src="<?php echo htmlspecialchars($item['icon']); ?>" class="h-12 w-12 object-cover rounded-full border-2 border-blue-500" onerror="this.src='data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 width=%2250%22 height=%2250%22%3E%3Ccircle fill=%22%23e5e7eb%22 cx=%2225%22 cy=%2225%22 r=%2225%22/%3E%3Ctext x=%2250%25%22 y=%2250%25%22 text-anchor=%22middle%22 dy=%22.3em%22 fill=%22%239ca3af%22 font-size=%2220%22%3E📦%3C/text%3E%3C/svg%3E';">
                                        <?php else: ?>
                                            <div class="h-12 w-12 bg-gray-200 rounded-full flex items-center justify-center">
                                                <i class="fas fa-image text-gray-400"></i>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="py-2 px-4 font-semibold"><?php echo htmlspecialchars($item['judul'] ?? ''); ?></td>
                                    <td class="py-2 px-4"><?php echo htmlspecialchars($item['deskripsi'] ?? ''); ?></td>
                                    <td class="py-2 px-4 text-center">
                                        <form method="POST" class="inline">
                                            <input type="hidden" name="action" value="edit">
                                            <input type="hidden" name="tabel" value="layanan">
                                            <input type="hidden" name="id" value="<?php echo $item['id']; ?>">
                                            <button type="submit" class="text-blue-500 hover:text-blue-700 mr-3"><i class="fas fa-edit"></i></button>
                                        </form>
                                        <form method="POST" class="inline" onsubmit="return confirm('Yakin hapus?')">
                                            <input type="hidden" name="action" value="hapus">
                                            <input type="hidden" name="tabel" value="layanan">
                                            <input type="hidden" name="id" value="<?php echo $item['id']; ?>">
                                            <button type="submit" class="text-red-500 hover:text-red-700"><i class="fas fa-trash"></i></button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Kelola Galeri -->
            <div class="bg-white p-4 md:p-6 rounded-lg shadow">
                <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3 mb-4">
                    <h2 class="text-xl md:text-2xl font-bold"><i class="fas fa-images mr-2 text-blue-600"></i>Kelola Galeri</h2>
                    <form method="POST" class="w-full sm:w-auto">
                        <input type="hidden" name="action" value="tambah">
                        <input type="hidden" name="tabel" value="galeri">
                        <button type="submit" class="w-full sm:w-auto bg-green-500 hover:bg-green-600 text-white font-bold py-2 px-4 rounded">
                            <i class="fas fa-plus mr-2"></i>Tambah
                        </button>
                    </form>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                    <?php foreach ($galeri as $item): $item = (array)$item; ?>
                        <div class="border rounded-lg overflow-hidden hover:shadow-lg transition">
                            <?php if (!empty($item['gambar'])): ?>
                                <img src="<?php echo htmlspecialchars($item['gambar']); ?>" class="w-full h-48 object-cover" onerror="this.src='data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 width=%22300%22 height=%22200%22%3E%3Crect fill=%22%23e5e7eb%22 width=%22300%22 height=%22200%22/%3E%3Ctext x=%2250%25%22 y=%2250%25%22 text-anchor=%22middle%22 dy=%22.3em%22 fill=%22%239ca3af%22 font-size=%2230%22%3E🖼️%3C/text%3E%3C/svg%3E';">
                            <?php else: ?>
                                <div class="w-full h-48 bg-gray-200 flex items-center justify-center">
                                    <i class="fas fa-image text-gray-400 text-4xl"></i>
                                </div>
                            <?php endif; ?>
                            <div class="p-4">
                                <p class="font-semibold mb-3 line-clamp-2"><?php echo htmlspecialchars($item['caption'] ?? ''); ?></p>
                                <div class="flex gap-2">
                                    <form method="POST" class="flex-1">
                                        <input type="hidden" name="action" value="edit">
                                        <input type="hidden" name="tabel" value="galeri">
                                        <input type="hidden" name="id" value="<?php echo $item['id']; ?>">
                                        <button type="submit" class="w-full bg-blue-500 hover:bg-blue-600 text-white py-2 rounded text-sm">
                                            <i class="fas fa-edit"></i> Edit
                                        </button>
                                    </form>
                                    <form method="POST" class="flex-1" onsubmit="return confirm('Yakin hapus?')">
                                        <input type="hidden" name="action" value="hapus">
                                        <input type="hidden" name="tabel" value="galeri">
                                        <input type="hidden" name="id" value="<?php echo $item['id']; ?>">
                                        <button type="submit" class="w-full bg-red-500 hover:bg-red-600 text-white py-2 rounded text-sm">
                                            <i class="fas fa-trash"></i> Hapus
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
    </main>

    <script>
        // Auto hide alerts
        setTimeout(() => {
            document.querySelectorAll('[class*="bg-red-100"], [class*="bg-green-100"]').forEach(el => {
                el.style.transition = 'opacity 0.5s';
                el.style.opacity = '0';
                setTimeout(() => el.remove(), 500);
            });
        }, 3000);

        // Confirm before leaving page with unsaved changes
        let formChanged = false;
        document.querySelectorAll('form input, form textarea').forEach(input => {
            input.addEventListener('change', () => formChanged = true);
        });

        document.querySelectorAll('form').forEach(form => {
            form.addEventListener('submit', () => formChanged = false);
        });

        window.addEventListener('beforeunload', (e) => {
            if (formChanged) {
                e.preventDefault();
                e.returnValue = '';
            }
        });

        // Image preview
        document.querySelectorAll('input[type="file"]').forEach(input => {
            input.addEventListener('change', function(e) {
                const file = e.target.files[0];
                if (file && file.type.startsWith('image/')) {
                    // Check file size (5MB = 5 * 1024 * 1024 bytes)
                    if (file.size > 5 * 1024 * 1024) {
                        alert('Ukuran file terlalu besar! Maksimal 5MB');
                        this.value = '';
                        return;
                    }

                    const reader = new FileReader();
                    reader.onload = function(event) {
                        // Remove existing preview
                        const existingPreview = input.parentElement.querySelector('.preview-image');
                        if (existingPreview) existingPreview.remove();

                        // Create new preview
                        const preview = document.createElement('div');
                        preview.className = 'preview-image mt-2';
                        preview.innerHTML = `
                            <p class="text-xs text-gray-500 mb-1">Preview:</p>
                            <img src="${event.target.result}" class="h-20 w-20 object-cover rounded border">
                        `;
                        input.parentElement.appendChild(preview);
                    };
                    reader.readAsDataURL(file);
                }
            });
        });
    </script>
</body>

</html>