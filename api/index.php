<?php
session_save_path('/tmp');  // wajib di serverless Vercel
session_start();
require __DIR__ . '/config.php';
require __DIR__ . '/../vendor/autoload.php';


// --- Supabase Helper ---
class SupabaseHelper
{
    private $url, $key, $client;

    public function __construct($url, $key)
    {
        $this->url = $url;
        $this->key = $key;
        $this->client = new \GuzzleHttp\Client([
            'base_uri' => $url . '/rest/v1/',
            'headers' => [
                'apikey' => $key,
                'Authorization' => 'Bearer ' . $key,
                'Content-Type' => 'application/json'
            ]
        ]);
    }

    public function from($table)
    {
        return new class($this->client, $table)
        {
            private $client, $table, $filters = [], $selectFields = '*', $orderBy = '';

            public function __construct($client, $table)
            {
                $this->client = $client;
                $this->table = $table;
            }

            public function select($fields = '*')
            {
                $this->selectFields = $fields;
                return $this;
            }

            public function eq($col, $val)
            {
                $this->filters[] = "$col=eq.$val";
                return $this;
            }

            public function order($col, $dir = 'asc')
            {
                $this->orderBy = "$col.$dir";
                return $this;
            }

            public function execute()
            {
                $q = '?select=' . $this->selectFields;
                if (!empty($this->filters)) $q .= '&' . implode('&', $this->filters);
                if (!empty($this->orderBy)) $q .= '&order=' . $this->orderBy;

                try {
                    $res = $this->client->get($this->table . $q);
                    return json_decode($res->getBody()->getContents());
                } catch (Exception $e) {
                    return [];
                }
            }
        };
    }
}

$client = new SupabaseHelper($supabaseUrl, $supabaseKey);
$layanan = $client->from('layanan')->select('*')->order('id')->execute();
$galeri = $client->from('galeri')->select('*')->order('id')->execute();
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Mas Tumbas — Solusi Pengiriman Cepat & Terpercaya</title>
    <meta name="description" content="Layanan antar barang, makanan, belanja, dan dokumen dengan cepat, aman, dan terjangkau. Siap melayani 24/7!">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" />
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: '#1e40af',
                        secondary: '#3b82f6',
                        accent: '#10b981'
                    },
                    backgroundImage: {
                        'diagonal-lines': "url(\"data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cpath d='M0 0L60 60M60 0L0 60' stroke='%23f1f5f9' stroke-width='2'/%3E%3C/svg%3E\")"
                    }
                }
            }
        }
    </script>
    <style>
        html {
            scroll-behavior: smooth;
        }

        .scrollable-x {
            display: flex;
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
            scrollbar-width: thin;
            padding-bottom: 8px;
            gap: 16px;
        }

        .scrollable-x::-webkit-scrollbar {
            height: 6px;
        }

        .scrollable-x::-webkit-scrollbar-thumb {
            background-color: #cbd5e1;
            border-radius: 3px;
        }

        .gallery-image-container {
            position: relative;
            width: 100%;
            padding-bottom: 100%;
            /* 1:1 aspect ratio */
            overflow: hidden;
            border-radius: 0.5rem;
        }

        .gallery-image-container img {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            object-fit: contain;
            background-color: #f8fafc;
        }
    </style>
</head>

<body class="bg-gray-50 bg-[length:60px_60px] bg-diagonal-lines text-gray-800" style="font-family: 'Poppins', sans-serif;">


    <!-- Sticky Navbar -->
    <nav class="bg-white shadow-md sticky top-0 z-50">
        <div class="container mx-auto px-4">
            <div class="flex justify-between items-center py-3">
                <div class="flex items-center gap-2">
                    <?php if (file_exists('log.png')): ?>
                        <div class="w-16 h-16 rounded-full overflow-hidden border-2 border-blue-500 shadow-md">
                            <img src="/fixlogo.jpg" alt="Logo Mas Tumbas" class="w-full h-full object-cover">
                        </div>

                    <?php else: ?>
                        <div class="w-16 h-16 rounded-full overflow-hidden border-2 border-blue-500 shadow-md">
                            <img src="/fixlogo.jpg" alt="Logo Mas Tumbas" class="w-full h-full object-cover">
                        </div>
                    <?php endif; ?>
                    <span class="font-bold text-blue-800 text-lg">MAS TUMBAS</span>
                </div>

                <div class="hidden md:flex gap-6">
                    <a href="#beranda" class="font-medium hover:text-blue-600 transition">Beranda</a>
                    <a href="#layanan" class="font-medium hover:text-blue-600 transition">Layanan</a>
                    <a href="#galeri" class="font-medium hover:text-blue-600 transition">Galeri</a>
                    <a href="#biaya" class="font-medium hover:text-blue-600 transition">Biaya</a>
                </div>

                <button id="mobile-menu-button" class="md:hidden text-gray-700 focus:outline-none">
                    <i class="fas fa-bars text-xl"></i>
                </button>
            </div>

            <div id="mobile-menu" class="hidden md:hidden py-4 border-t">
                <div class="flex flex-col gap-3">
                    <a href="#beranda" class="font-medium py-2 hover:text-blue-600">Beranda</a>
                    <a href="#layanan" class="font-medium py-2 hover:text-blue-600">Layanan</a>
                    <a href="#galeri" class="font-medium py-2 hover:text-blue-600">Galeri</a>
                    <a href="#biaya" class="font-medium py-2 hover:text-blue-600">Biaya</a>
                </div>
            </div>
        </div>
    </nav>

    <!-- Hero -->
    <header id="beranda" class="bg-gradient-to-r from-blue-700 to-blue-900 text-white py-10">
        <div class="container mx-auto px-4 text-center">
            <?php if (file_exists('/fixlogo.jpg')): ?>
                <div class="w-28 h-28 mx-auto rounded-full overflow-hidden border-4 border-blue-400 shadow-xl mb-4 bg-white p-1">
                    <img src="/fixlogo.jpg" alt="Logo Mas Tumbas" class="w-full h-full object-contain">
                </div>


            <?php else: ?>
                <div class="w-24 h-24 mx-auto rounded-full bg-white flex items-center justify-center text-5xl mb-4 shadow-lg">
                    🛵
                </div>
            <?php endif; ?>
            <h1 class="text-3xl md:text-4xl font-bold tracking-tight">MAS TUMBAS</h1>
            <p class="text-lg md:text-xl font-medium mt-2">Solusi Pengiriman Cepat & Terpercaya</p>
        </div>
    </header>

    <!-- Main Content -->
    <main class="container mx-auto px-4 py-8 lg:py-12 space-y-12">

        <!-- Butuh Bantuan -->
        <section class="max-w-4xl mx-auto">
            <div class="bg-white rounded-xl p-6 shadow-lg hover:shadow-xl transition-shadow">
                <h2 class="text-xl font-bold mb-3 text-gray-900">
                    <i class="fas fa-headset mr-2 text-blue-600"></i>Butuh Bantuan?
                </h2>
                <p class="text-gray-600 mb-4 text-sm">Tim support kami siap membantu 24/7</p>

                <div class="space-y-3">
                    <a href="tel:081952050296" class="flex items-center gap-3 p-3 rounded-lg bg-blue-50 hover:bg-blue-100 transition-colors">
                        <i class="fas fa-phone text-blue-600 text-xl"></i>
                        <div>
                            <div class="font-medium text-gray-900">Telepon</div>
                            <div class="text-sm text-blue-600">819-5205-0296</div>
                        </div>
                    </a>

                    <a href="mailto:mastumbas25@gmail.com" class="flex items-center gap-3 p-3 rounded-lg bg-blue-50 hover:bg-blue-100 transition-colors">
                        <i class="fas fa-envelope text-blue-600 text-xl"></i>
                        <div>
                            <div class="font-medium text-gray-900">Email</div>
                            <div class="text-sm text-blue-600 break-all">mastumbas25@gmail.com</div>
                        </div>
                    </a>

                    <a href="https://wa.me/6281952050296
" target="_blank" class="flex items-center gap-3 p-3 rounded-lg bg-green-50 hover:bg-green-100 transition-colors">
                        <i class="fab fa-whatsapp text-green-600 text-xl"></i>
                        <div>
                            <div class="font-medium text-gray-900">WhatsApp</div>
                            <div class="text-sm text-green-600">Mau suruh apa hari ini?</div>
                        </div>
                    </a>
                </div>
            </div>
        </section>


        <!-- Layanan Section -->
        <section id="layanan">
            <h2 class="text-2xl md:text-3xl font-bold text-gray-900 mb-6 max-w-4xl mx-auto">
                <i class="fas fa-box-open text-blue-600 mr-2"></i>Layanan Kami
            </h2>

            <?php if (!empty($layanan)): ?>
                <div class="scrollable-x pb-4 max-w-4xl mx-auto">
                    <?php foreach ($layanan as $item): ?>
                        <div class="flex-shrink-0 w-48 bg-white rounded-xl shadow-md hover:shadow-xl transition-shadow p-5">
                            <div class="w-20 h-20 mx-auto mb-4 flex items-center justify-center bg-gradient-to-br from-blue-50 to-blue-100 rounded-xl">
                                <?php if (!empty($item->icon)): ?>
                                    <img src="<?= htmlspecialchars($item->icon) ?>" alt="Icon" class="w-12 h-12 object-contain">
                                <?php else: ?>
                                    <i class="fas fa-box text-blue-600 text-3xl"></i>
                                <?php endif; ?>
                            </div>
                            <h3 class="font-bold text-center text-gray-900 mb-2 text-base">
                                <?= htmlspecialchars($item->judul ?? 'Layanan') ?>
                            </h3>
                            <p class="text-xs text-gray-600 text-center leading-relaxed">
                                <?= htmlspecialchars($item->deskripsi ?? '') ?>
                            </p>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="bg-gray-100 rounded-xl p-8 text-center max-w-4xl mx-auto">
                    <i class="fas fa-box-open text-6xl text-gray-400 mb-4"></i>
                    <p class="text-gray-500">Belum ada layanan tersedia.</p>
                </div>
            <?php endif; ?>
        </section>

        <!-- Galeri Section -->
        <section id="galeri">
            <h2 class="text-2xl md:text-3xl font-bold text-gray-900 mb-6 max-w-4xl mx-auto">
                <i class="fas fa-images text-blue-600 mr-2"></i>Galeri Kegiatan
            </h2>

            <?php if (!empty($galeri)): ?>
                <div class="scrollable-x pb-4 max-w-4xl mx-auto">
                    <?php foreach ($galeri as $g): ?>
                        <div class="flex-shrink-0 w-64">
                            <div class="bg-white rounded-lg shadow-md hover:shadow-xl transition-shadow overflow-hidden">
                                <div class="gallery-image-container">
                                    <?php if (!empty($g->gambar)): ?>
                                        <img src="<?= htmlspecialchars($g->gambar) ?>"
                                            alt="<?= htmlspecialchars($g->caption ?? 'Galeri') ?>"
                                            loading="lazy">
                                    <?php else: ?>
                                        <div class="absolute inset-0 bg-gradient-to-br from-blue-100 to-blue-200 flex items-center justify-center">
                                            <i class="fas fa-image text-blue-400 text-4xl"></i>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="p-4">
                                    <p class="text-sm font-semibold text-gray-800 text-center">
                                        <?= htmlspecialchars($g->caption ?? 'Tanpa caption') ?>
                                    </p>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="bg-gray-100 rounded-xl p-8 text-center max-w-4xl mx-auto">
                    <i class="fas fa-images text-6xl text-gray-400 mb-4"></i>
                    <p class="text-gray-500">Belum ada foto galeri.</p>
                </div>
            <?php endif; ?>
        </section>

        <!-- Biaya Section -->
        <section id="biaya" class="max-w-4xl mx-auto">
            <div class="bg-gradient-to-br from-blue-600 to-blue-800 rounded-2xl p-6 md:p-8 text-white shadow-xl">
                <h2 class="text-2xl md:text-3xl font-bold mb-4 flex items-center gap-2">
                    <i class="fas fa-coins"></i> Informasi Biaya Layanan
                </h2>
                <ul class="space-y-3 text-blue-50 text-sm md:text-base">
                    <li class="flex items-start gap-3">
                        <i class="fas fa-check-circle text-emerald-300 mt-1 flex-shrink-0"></i>
                        <span>Biaya disesuaikan berdasarkan jenis layanan, jarak tempuh, berat/volume barang, dan tingkat kesulitan.</span>
                    </li>
                    <li class="flex items-start gap-3">
                        <i class="fas fa-check-circle text-emerald-300 mt-1 flex-shrink-0"></i>
                        <span>Estimasi biaya diberikan sebelum konfirmasi order — transparan tanpa biaya tersembunyi.</span>
                    </li>
                    <li class="flex items-start gap-3">
                        <i class="fas fa-check-circle text-emerald-300 mt-1 flex-shrink-0"></i>
                        <span>📍 Wilayah: Purwokerto, Paguyangan dan sekitarnya</span>
                    </li>
                    <li class="flex items-start gap-3">
                        <i class="fas fa-check-circle text-emerald-300 mt-1 flex-shrink-0"></i>
                        <span>📞 CP:(Admine) ‪+62 819-5205-0296‬</span>
                    </li>
                    <li class="flex items-start gap-3">
                        <i class="fas fa-check-circle text-emerald-300 mt-1 flex-shrink-0"></i>
                        <span>Yuk, tinggal chat — langsung diantar cepat dan aman! </span>
                    </li>
                    <li class="flex items-start gap-3">
                        <i class="fas fa-exclamation-triangle text-yellow-300 mt-1 flex-shrink-0"></i>
                        <span><strong>Peringatan:</strong> Mas Tumbas tidak bertanggung jawab atas transaksi langsung yang dilakukan pelanggan dengan mitra (tidak melalui admin).</span>
                    </li>
                </ul>
            </div>
        </section>
        <!-- Kolom Komentar -->
        <section class="max-w-4xl mx-auto">
            <div class="bg-white rounded-xl p-6 shadow-lg hover:shadow-xl transition-shadow">
                <h2 class="text-xl font-bold mb-4 text-gray-900">
                    <i class="fas fa-comment-dots mr-2 text-blue-600"></i>Kirim Komentar
                </h2>
                <p class="text-gray-600 mb-4 text-sm">Pesan Anda akan langsung dikirim ke WhatsApp admin kami.</p>

                <form id="komentarForm" class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Nama Anda</label>
                        <input type="text" id="namaKomentar" placeholder="Masukkan nama Anda" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none" required>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Pesan</label>
                        <textarea id="pesanKomentar" rows="3" placeholder="Tulis pesan Anda di sini..." class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none" required></textarea>
                    </div>
                    <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-medium py-2.5 rounded-lg transition flex items-center justify-center gap-2">
                        <i class="fab fa-whatsapp"></i> Kirim ke WhatsApp Admin
                    </button>
                </form>
            </div>
        </section>

    </main>

    <!-- Footer -->
    <footer class="bg-gray-900 text-white py-8 mt-12">
        <div class="container mx-auto px-4 text-center">
            <div class="flex items-center justify-center gap-2 mb-4">
                <span class="text-2xl">🛵</span>
                <span class="font-bold text-xl">MAS TUMBAS</span>
            </div>
            <p class="text-gray-400 text-sm">
                &copy; <?= date('Y') ?> Mas Tumbas. All rights reserved.
            </p>
            <p class="text-gray-500 text-xs mt-2">
                <a href="/admin" class="hover:text-blue-400 transition">Admin Panel</a>
            </p>

        </div>
    </footer>

    <script>
        // Mobile menu toggle
        document.getElementById('mobile-menu-button').addEventListener('click', function() {
            const menu = document.getElementById('mobile-menu');
            menu.classList.toggle('hidden');
        });

        document.querySelectorAll('#mobile-menu a').forEach(link => {
            link.addEventListener('click', () => {
                document.getElementById('mobile-menu').classList.add('hidden');
            });
        });

        // Komentar form handler
        document.getElementById('komentarForm').addEventListener('submit', function(e) {
            e.preventDefault();

            const nama = document.getElementById('namaKomentar').value.trim();
            const pesan = document.getElementById('pesanKomentar').value.trim();

            if (!nama || !pesan) {
                alert('Mohon isi nama dan pesan Anda.');
                return;
            }

            // Format pesan untuk WhatsApp
            const pesanWA = `[Komentar Website]%0ANama: ${nama}%0APesan: ${pesan}`;
            const waUrl = `https://wa.me/6285280006900?text=${pesanWA}`;

            // Buka di tab baru
            window.open(waUrl, '_blank');

            // Reset form
            this.reset();

            // Notifikasi
            alert('Pesan Anda sedang dikirim ke WhatsApp admin. Jendela WhatsApp akan terbuka sebentar lagi.');
        });
    </script>


    <script>
        const text = `Mas Tumbas adalah layanan pesan-antar serba bisa yang siap membantu kamu memenuhi berbagai kebutuhan harian tanpa harus keluar rumah. 
    Dari beli makanan, jajan, rokok, hingga isi pulsa. Semua bisa kamu pesan lewat chat saja. 
    Kami juga menyediakan layanan antar-jemput untuk memudahkan mobilitasmu di sekitar Purwokerto. 
    Dengan pelayanan cepat, aman, dan ramah, Mas Tumbas hadir sebagai solusi praktis buat kamu yang ingin serba mudah dan hemat waktu. 
    Tinggal chat, langsung beres!`;

        function speakLongText(text) {
            const parts = text.match(/[^.!?]+[.!?]+/g) || [text]; // Pisahkan per kalimat
            let index = 0;

            function speakNext() {
                if (index < parts.length) {
                    const utterance = new SpeechSynthesisUtterance(parts[index].trim());
                    utterance.lang = 'id-ID';
                    utterance.rate = 1;
                    utterance.pitch = 1;
                    utterance.onend = () => {
                        index++;
                        speakNext(); // lanjut ke kalimat berikutnya
                    };
                    speechSynthesis.speak(utterance);
                }
            }

            speechSynthesis.cancel(); // hentikan sisa suara sebelumnya
            speakNext();
        }

        // Coba autoplay
        window.addEventListener('load', () => {
            try {
                speakLongText(text);
            } catch (e) {
                console.log('Autoplay diblokir, menunggu interaksi pengguna...');
            }
        });

        // Jika autoplay diblokir, jalankan setelah klik pertama
        document.addEventListener('click', () => {
            if (!speechSynthesis.speaking) {
                speakLongText(text);
            }
        }, {
            once: true
        });
    </script>



</body>

</html>