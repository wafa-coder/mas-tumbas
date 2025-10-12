<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test Upload Supabase</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }

        .container {
            background: white;
            padding: 40px;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            max-width: 500px;
            width: 100%;
        }

        h1 {
            color: #333;
            margin-bottom: 30px;
            text-align: center;
        }

        .upload-area {
            border: 3px dashed #667eea;
            border-radius: 15px;
            padding: 40px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s;
            margin-bottom: 20px;
        }

        .upload-area:hover {
            border-color: #764ba2;
            background: #f8f9ff;
        }

        input[type="file"] {
            display: none;
        }

        .upload-icon {
            font-size: 50px;
            margin-bottom: 10px;
        }

        button {
            width: 100%;
            padding: 15px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
            transition: transform 0.2s;
        }

        button:hover:not(:disabled) {
            transform: translateY(-2px);
        }

        button:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }

        .success {
            background: #e0ffe0;
            color: #0a0;
            padding: 20px;
            border-radius: 10px;
            margin-top: 20px;
            display: none;
        }

        .success img {
            max-width: 100%;
            border-radius: 10px;
            margin-top: 15px;
        }

        .error {
            background: #ffe0e0;
            color: #d00;
            padding: 15px;
            border-radius: 10px;
            margin-top: 20px;
            display: none;
        }

        .loading {
            text-align: center;
            margin-top: 20px;
            display: none;
        }

        .spinner {
            border: 4px solid #f3f3f3;
            border-top: 4px solid #667eea;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            animation: spin 1s linear infinite;
            margin: 0 auto;
        }

        @keyframes spin {
            0% {
                transform: rotate(0deg);
            }

            100% {
                transform: rotate(360deg);
            }
        }

        .file-info {
            margin-top: 10px;
            color: #666;
            font-size: 14px;
        }
    </style>
</head>

<body>
    <div class="container">
        <h1>📤 Test Upload</h1>

        <div class="upload-area" id="uploadArea">
            <div class="upload-icon">📁</div>
            <p>Klik untuk pilih gambar</p>
            <p style="font-size: 12px; color: #999; margin-top: 10px;">
                Maksimal 5MB (JPG, PNG, GIF, WEBP)
            </p>
            <div class="file-info" id="fileInfo"></div>
        </div>

        <input type="file" id="fileInput" accept="image/*">

        <button id="uploadBtn" disabled>Upload ke Supabase</button>

        <div class="loading" id="loading">
            <div class="spinner"></div>
            <p style="margin-top: 10px;">Uploading...</p>
        </div>

        <div class="success" id="success"></div>
        <div class="error" id="error"></div>
    </div>

    <script>
        // KONFIGURASI SUPABASE
        const SUPABASE_URL = 'https://voetxxrzcfmipxijcxzz.supabase.co';
        const SUPABASE_KEY = 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6InZvZXR4eHJ6Y2ZtaXB4aWpjeHp6Iiwicm9sZSI6ImFub24iLCJpYXQiOjE3NjAxMDAyOTQsImV4cCI6MjA3NTY3NjI5NH0.wEmsHiQxVyZTrYtPnAhjPdWEC4Z0A7K8M_noNI0h4Q0';
        const BUCKET_NAME = 'uploads';

        const uploadArea = document.getElementById('uploadArea');
        const fileInput = document.getElementById('fileInput');
        const uploadBtn = document.getElementById('uploadBtn');
        const fileInfo = document.getElementById('fileInfo');
        const loading = document.getElementById('loading');
        const successDiv = document.getElementById('success');
        const errorDiv = document.getElementById('error');

        let selectedFile = null;

        // Click area untuk pilih file
        uploadArea.addEventListener('click', () => {
            fileInput.click();
        });

        // File selected
        fileInput.addEventListener('change', (e) => {
            const file = e.target.files[0];
            if (file) {
                selectedFile = file;
                const size = (file.size / 1024 / 1024).toFixed(2);
                fileInfo.textContent = `📎 ${file.name} (${size} MB)`;
                uploadBtn.disabled = false;
            }
        });

        // Upload button
        uploadBtn.addEventListener('click', async () => {
            if (!selectedFile) return;

            // Hide messages
            successDiv.style.display = 'none';
            errorDiv.style.display = 'none';
            loading.style.display = 'block';
            uploadBtn.disabled = true;

            try {
                // Validasi
                const maxSize = 5 * 1024 * 1024; // 5MB
                if (selectedFile.size > maxSize) {
                    throw new Error('Ukuran file maksimal 5MB');
                }

                // Generate unique filename (pendek)
                const timestamp = Date.now();
                const random = Math.random().toString(36).substring(2, 7);
                const extension = selectedFile.name.split('.').pop().toLowerCase();
                const filename = `${random}${timestamp}.${extension}`;

                // Upload ke Supabase Storage
                const filePath = `${BUCKET_NAME}/${filename}`;
                const uploadUrl = `${SUPABASE_URL}/storage/v1/object/${filePath}`;

                const fileContent = await selectedFile.arrayBuffer();

                const response = await fetch(uploadUrl, {
                    method: 'POST',
                    headers: {
                        'Authorization': `Bearer ${SUPABASE_KEY}`,
                        'Content-Type': selectedFile.type,
                        'x-upsert': 'false'
                    },
                    body: fileContent
                });

                loading.style.display = 'none';

                if (response.ok) {
                    const publicUrl = `${SUPABASE_URL}/storage/v1/object/public/${filePath}`;

                    successDiv.innerHTML = `
                        <strong>✅ Upload Berhasil!</strong><br><br>
                        <strong>Filename:</strong> ${filename}<br>
                        <strong>URL:</strong> <a href="${publicUrl}" target="_blank">${publicUrl}</a>
                        <img src="${publicUrl}" alt="Uploaded" style="margin-top:15px">
                    `;
                    successDiv.style.display = 'block';

                    // Reset
                    selectedFile = null;
                    fileInput.value = '';
                    fileInfo.textContent = '';
                    uploadBtn.disabled = true;
                } else {
                    const errorData = await response.text();
                    throw new Error(`Upload gagal: ${errorData}`);
                }

            } catch (error) {
                loading.style.display = 'none';
                errorDiv.textContent = '❌ ' + error.message;
                errorDiv.style.display = 'block';
                uploadBtn.disabled = false;
                console.error('Error:', error);
            }
        });
    </script>
</body>

</html>