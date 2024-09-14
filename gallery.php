<?php
// Define the upload directory and allowed file types
$uploadDir = 'assets/img/gallery/';
$allowedTypes = ['jpg', 'jpeg', 'png', 'gif'];

// Function to sanitize file names
function sanitizeFileName($fileName) {
    return preg_replace("/[^a-zA-Z0-9.-]/", "_", $fileName);
}

// Handle file upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['images'])) {
    $uploadedFiles = $_FILES['images'];
    $uploadedCount = count($uploadedFiles['name']);
    $successCount = 0;
    $errors = [];

    for ($i = 0; $i < $uploadedCount; $i++) {
        $fileName = sanitizeFileName($uploadedFiles['name'][$i]);
        $fileTmpName = $uploadedFiles['tmp_name'][$i];
        $fileError = $uploadedFiles['error'][$i];
        $fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

        // Get custom name and description
        $customName = isset($_POST['imageNames'][$i]) ? sanitizeFileName($_POST['imageNames'][$i]) : '';
        $imageDescription = isset($_POST['imageDescriptions'][$i]) ? $_POST['imageDescriptions'][$i] : '';

        // Check if file type is allowed
        if (!in_array($fileExt, $allowedTypes)) {
            $errors[] = "File type not allowed for {$fileName}.";
            continue;
        }

        // Generate unique file name
        $newFileName = ($customName ? $customName . '_' : '') . uniqid('img_', true) . '.' . $fileExt;
        $uploadPath = $uploadDir . $newFileName;

        // Move uploaded file
        if (move_uploaded_file($fileTmpName, $uploadPath)) {
            // Add image info to JSON file
            $jsonFile = 'gallery_data.json';
            $galleryData = file_exists($jsonFile) ? json_decode(file_get_contents($jsonFile), true) : [];
            $galleryData[] = array(
                'id' => uniqid(),
                'filename' => $newFileName,
                'originalName' => $fileName,
                'customName' => $customName,
                'description' => $imageDescription,
                'uploadDate' => date('Y-m-d H:i:s')
            );
            file_put_contents($jsonFile, json_encode($galleryData, JSON_PRETTY_PRINT));
            $successCount++;
            
            // Send progress update
            echo json_encode(['status' => 'progress', 'message' => "Uploaded {$successCount} of {$uploadedCount} files."]);
            ob_flush();
            flush();
        } else {
            $errors[] = "Failed to upload {$fileName}.";
        }
    }

    if ($successCount > 0) {
        $message = "{$successCount} file(s) uploaded successfully.";
        if (!empty($errors)) {
            $message .= " Errors: " . implode(" ", $errors);
        }
        echo json_encode(['status' => 'success', 'message' => $message]);
    } else {
        echo json_encode(['status' => 'error', 'message' => implode(" ", $errors)]);
    }
    exit;
}

// Handle image deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete'])) {
    $jsonFile = 'gallery_data.json';
    $galleryData = file_exists($jsonFile) ? json_decode(file_get_contents($jsonFile), true) : [];

    if ($_POST['delete'] === 'all') {
        // Delete all images
        foreach ($galleryData as $image) {
            if (file_exists($uploadDir . $image['filename'])) {
                unlink($uploadDir . $image['filename']);
            }
        }
        $galleryData = [];
    } else {
        // Delete individual image
        $imageId = $_POST['delete'];
        $galleryData = array_filter($galleryData, function($image) use ($imageId, $uploadDir) {
            if ($image['id'] === $imageId) {
                if (file_exists($uploadDir . $image['filename'])) {
                    unlink($uploadDir . $image['filename']);
                }
                return false;
            }
            return true;
        });
    }

    file_put_contents($jsonFile, json_encode(array_values($galleryData), JSON_PRETTY_PRINT));
    echo json_encode(['status' => 'success', 'message' => 'Image(s) deleted successfully.']);
    exit;
}

// Load gallery data
$jsonFile = 'gallery_data.json';
$galleryData = file_exists($jsonFile) ? json_decode(file_get_contents($jsonFile), true) : [];

// Debug information
error_reporting(E_ALL);
ini_set('display_errors', 1);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gallery Upload Manager</title>
    <link href="assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <h2>Upload Images to Gallery</h2>
        <form id="uploadForm" enctype="multipart/form-data">
            <div class="mb-3">
                <label for="images" class="form-label">Select Images</label>
                <input type="file" class="form-control" id="images" name="images[]" accept=".jpg,.jpeg,.png,.gif" multiple required>
            </div>
            <div id="imageDetails"></div>
            <button type="submit" class="btn btn-primary">Upload</button>
        </form>
        <div id="progressBar" class="progress mt-3" style="display: none;">
            <div class="progress-bar" role="progressbar" style="width: 0%;" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100">0%</div>
        </div>
        <div id="message" class="mt-3"></div>

        <h3 class="mt-5">Gallery Images</h3>
        <div id="galleryContainer" class="row">
            <?php foreach ($galleryData as $image): ?>
                <div class="col-md-4 mb-3">
                    <div class="card">
                        <img src="<?php echo $uploadDir . $image['filename']; ?>" class="card-img-top" alt="<?php echo htmlspecialchars($image['customName'] ?: $image['originalName']); ?>">
                        <div class="card-body">
                            <h5 class="card-title"><?php echo htmlspecialchars($image['customName'] ?: $image['originalName']); ?></h5>
                            <p class="card-text"><?php echo htmlspecialchars($image['description']); ?></p>
                            <button class="btn btn-danger btn-sm delete-image" data-id="<?php echo $image['id']; ?>">Delete</button>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <button id="deleteAll" class="btn btn-danger mt-3">Delete All Images</button>
    </div>

    <script src="assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const uploadForm = document.getElementById('uploadForm');
            const imagesInput = document.getElementById('images');
            const imageDetails = document.getElementById('imageDetails');
            const messageDiv = document.getElementById('message');
            const progressBar = document.getElementById('progressBar');
            const progressBarInner = progressBar.querySelector('.progress-bar');
            const deleteAllBtn = document.getElementById('deleteAll');
            const galleryContainer = document.getElementById('galleryContainer');

            imagesInput.addEventListener('change', function() {
                imageDetails.innerHTML = '';
                for (let i = 0; i < this.files.length; i++) {
                    imageDetails.innerHTML += `
                        <div class="mb-3">
                            <label class="form-label">Image ${i + 1}: ${this.files[i].name}</label>
                            <input type="text" class="form-control" name="imageNames[]" placeholder="Custom name (optional)">
                            <textarea class="form-control mt-2" name="imageDescriptions[]" rows="2" placeholder="Image description"></textarea>
                        </div>
                    `;
                }
            });

            uploadForm.addEventListener('submit', function(e) {
                e.preventDefault();
                const formData = new FormData(this);
                const totalFiles = imagesInput.files.length;
                let uploadedFiles = 0;

                progressBar.style.display = 'flex';
                progressBarInner.style.width = '0%';
                progressBarInner.textContent = '0%';

                const xhr = new XMLHttpRequest();
                xhr.open('POST', 'gallery.php', true);
                xhr.upload.onprogress = function(e) {
                    if (e.lengthComputable) {
                        const percentComplete = (e.loaded / e.total) * 100;
                        progressBarInner.style.width = percentComplete + '%';
                        progressBarInner.textContent = Math.round(percentComplete) + '%';
                    }
                };
                xhr.onreadystatechange = function() {
                    if (xhr.readyState === XMLHttpRequest.DONE) {
                        if (xhr.status === 200) {
                            const response = JSON.parse(xhr.responseText);
                            messageDiv.innerHTML = `<div class="alert alert-${response.status === 'success' ? 'success' : 'danger'}">${response.message}</div>`;
                            if (response.status === 'success') {
                                uploadForm.reset();
                                imageDetails.innerHTML = '';
                                // Refresh the page after successful upload
                                window.location.reload();
                            }
                        } else {
                            messageDiv.innerHTML = '<div class="alert alert-danger">An error occurred. Please try again.</div>';
                        }
                        progressBar.style.display = 'none';
                    }
                };
                xhr.send(formData);
            });

            // Delete individual image
            galleryContainer.addEventListener('click', function(e) {
                if (e.target.classList.contains('delete-image')) {
                    const imageId = e.target.dataset.id;
                    if (confirm('Are you sure you want to delete this image?')) {
                        deleteImage(imageId);
                    }
                }
            });

            // Delete all images
            deleteAllBtn.addEventListener('click', function() {
                if (confirm('Are you sure you want to delete all images?')) {
                    deleteImage('all');
                }
            });

            function deleteImage(id) {
                const formData = new FormData();
                formData.append('delete', id);

                fetch('gallery.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    messageDiv.innerHTML = `<div class="alert alert-${data.status === 'success' ? 'success' : 'danger'}">${data.message}</div>`;
                    if (data.status === 'success') {
                        location.reload(); // Reload the page to update the gallery
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    messageDiv.innerHTML = '<div class="alert alert-danger">An error occurred. Please try again.</div>';
                });
            }
        });
    </script>
</body>
</html>
