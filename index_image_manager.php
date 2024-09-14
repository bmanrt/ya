<?php
class IndexMediaManager {
    private $mediaPaths = [
        'hero' => 'assets/img/hero-bg.jpg',
        'about' => 'assets/img/about.jpg',
        'about1' => 'assets/img/about-1.jpg',
        'about2' => 'assets/img/about-2.jpg',
        'about3' => 'assets/img/about-3.jpg',
        'features1' => 'assets/img/features-1.svg',
        'features2' => 'assets/img/features-2.svg',
        'features3' => 'assets/img/features-3.svg',
        'features4' => 'assets/img/features-4.svg',
        'campaignGoalsBg' => 'assets/img/campaign-goals-bg.jpg',
        'campaignBackground' => 'assets/img/campaign-background.jpg', // Added for campaign-background
        'logo' => 'assets/img/logo.png',
        'icon' => 'assets/img/favicon.png',
        'mobilizingYouth' => 'assets/img/mobilizing-youth.jpg',
        'keyMilestones' => 'assets/img/key-milestones.jpg',
        'campusOutreach' => 'assets/img/campus-outreach.jpg',
        'globalYouthImpact' => 'assets/img/global-youth-impact.jpg'
    ];

    public function replaceMedia($key, $newMediaPath) {
        if (!array_key_exists($key, $this->mediaPaths)) {
            throw new InvalidArgumentException("Invalid media key: $key");
        }

        $oldMediaPath = $this->mediaPaths[$key];
        if (!file_exists($newMediaPath)) {
            throw new RuntimeException("New media file does not exist: $newMediaPath");
        }

        // Check if it's an about section image and validate file extension
        if (strpos($key, 'about') === 0) {
            $allowedExtensions = ['png', 'jpg', 'jpeg'];
            $fileExtension = strtolower(pathinfo($newMediaPath, PATHINFO_EXTENSION));
            if (!in_array($fileExtension, $allowedExtensions)) {
                throw new RuntimeException("Invalid file type for about section. Allowed types: png, jpg, jpeg");
            }
        }

        if (!rename($newMediaPath, $oldMediaPath)) {
            throw new RuntimeException("Failed to replace media: $oldMediaPath");
        }

        return true;
    }

    public function deleteMedia($key) {
        if (!array_key_exists($key, $this->mediaPaths)) {
            throw new InvalidArgumentException("Invalid media key: $key");
        }

        $mediaPath = $this->mediaPaths[$key];
        if (!file_exists($mediaPath) || !unlink($mediaPath)) {
            throw new RuntimeException("Failed to delete media: $mediaPath");
        }

        unset($this->mediaPaths[$key]);
        return true;
    }

    public function getMediaPath($key) {
        if (!array_key_exists($key, $this->mediaPaths)) {
            throw new InvalidArgumentException("Invalid media key: $key");
        }
        return $this->mediaPaths[$key];
    }

    public function getAllMediaPaths() {
        return $this->mediaPaths;
    }
}

$mediaManager = new IndexMediaManager();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $response = ['success' => false, 'message' => ''];

    try {
        if (!isset($_POST['action'])) {
            throw new InvalidArgumentException('Action not specified');
        }

        switch ($_POST['action']) {
            case 'replace':
                if (!isset($_POST['key']) || !isset($_FILES['newMedia'])) {
                    throw new InvalidArgumentException('Missing key or new media file');
                }
                if ($_FILES['newMedia']['error'] !== UPLOAD_ERR_OK) {
                    throw new RuntimeException('File upload failed. Error code: ' . $_FILES['newMedia']['error']);
                }
                $tempPath = $_FILES['newMedia']['tmp_name'];
                $mediaManager->replaceMedia($_POST['key'], $tempPath);
                $response['success'] = true;
                $response['message'] = 'Media replaced successfully';
                break;

            case 'delete':
                if (!isset($_POST['key'])) {
                    throw new InvalidArgumentException('Missing key for deletion');
                }
                $mediaManager->deleteMedia($_POST['key']);
                $response['success'] = true;
                $response['message'] = 'Media deleted successfully';
                break;

            default:
                throw new InvalidArgumentException('Invalid action specified');
        }
    } catch (Exception $e) {
        $response['message'] = $e->getMessage();
        http_response_code(400);
    }

    echo json_encode($response);
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Media Manager</title>
    <link href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <h1>Media Manager</h1>
        <div id="alert-container"></div>
        <table class="table">
            <thead>
                <tr>
                    <th>Key</th>
                    <th>Path</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($mediaManager->getAllMediaPaths() as $key => $path): ?>
                <tr>
                    <td><?php echo htmlspecialchars($key); ?></td>
                    <td><?php echo htmlspecialchars($path); ?></td>
                    <td>
                        <form class="media-form" method="POST" enctype="multipart/form-data">
                            <input type="hidden" name="action" value="replace">
                            <input type="hidden" name="key" value="<?php echo htmlspecialchars($key); ?>">
                            <div class="input-group">
                                <div class="custom-file">
                                    <input type="file" class="custom-file-input" name="newMedia" required accept="<?php echo strpos($key, 'about') === 0 ? 'image/png,image/jpeg' : 'image/*,video/*'; ?>">
                                    <label class="custom-file-label">Choose file</label>
                                </div>
                                <div class="input-group-append">
                                    <button type="submit" class="btn btn-primary">Replace</button>
                                </div>
                            </div>
                        </form>
                        <form class="media-form mt-2" method="POST">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="key" value="<?php echo htmlspecialchars($key); ?>">
                            <button type="submit" class="btn btn-danger btn-sm">Delete</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    <script>
    $(document).ready(function() {
        $('.media-form').on('submit', function(e) {
            e.preventDefault();
            var form = $(this);
            var formData = new FormData(this);

            $.ajax({
                url: window.location.href,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    showAlert(response.message, response.success ? 'success' : 'danger');
                    if (response.success) {
                        setTimeout(function() {
                            location.reload();
                        }, 2000);
                    }
                },
                error: function(xhr) {
                    showAlert('Error: ' + (xhr.responseJSON ? xhr.responseJSON.message : 'Unknown error occurred'), 'danger');
                }
            });
        });

        function showAlert(message, type) {
            $('#alert-container').html(
                '<div class="alert alert-' + type + ' alert-dismissible fade show" role="alert">' +
                message +
                '<button type="button" class="close" data-dismiss="alert" aria-label="Close">' +
                '<span aria-hidden="true">&times;</span>' +
                '</button>' +
                '</div>'
            );
        }

        // Update file input label with selected filename
        $('.custom-file-input').on('change', function() {
            var fileName = $(this).val().split('\\').pop();
            $(this).next('.custom-file-label').html(fileName);
        });
    });
    </script>
</body>
</html>
