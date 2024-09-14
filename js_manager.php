<?php
class JSManager {
    private $jsFile = 'assets/js/main.js';

    public function __construct() {
        if (!file_exists($this->jsFile)) {
            throw new Exception("JS file not found: {$this->jsFile}");
        }
    }

    public function getJSContent() {
        return file_get_contents($this->jsFile);
    }

    public function updateJS($newContent) {
        if (file_put_contents($this->jsFile, $newContent) === false) {
            throw new Exception("Failed to update JS file");
        }
    }

    public function getAllJSFunctions() {
        $js = $this->getJSContent();
        preg_match_all('/function\s+(\w+)\s*\([^)]*\)\s*{/', $js, $matches);
        return $matches[1];
    }

    public function updateFunction($functionName, $newContent) {
        $js = $this->getJSContent();
        $pattern = '/function\s+' . preg_quote($functionName, '/') . '\s*\([^)]*\)\s*{[^}]*}/s';
        $updatedFunction = "function {$functionName}() {\n{$newContent}\n}";
        $js = preg_replace($pattern, $updatedFunction, $js);
        $this->updateJS($js);
        return true;
    }

    public function getFunctionContent($functionName) {
        $js = $this->getJSContent();
        $pattern = '/function\s+' . preg_quote($functionName, '/') . '\s*\([^)]*\)\s*{([^}]*)}/s';
        if (preg_match($pattern, $js, $matches)) {
            return trim($matches[1]);
        }
        return '';
    }
}

$jsManager = new JSManager();

$message = '';
$messageType = '';

// Initialize session
session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_function'])) {
        try {
            $functionName = $_POST['function_name'];
            $functionContent = $_POST['function_content'];
            if ($jsManager->updateFunction($functionName, $functionContent)) {
                $message = "JS function updated successfully.";
                $messageType = "success";
                // Store the current function name in session
                $_SESSION['current_function'] = $functionName;
            } else {
                $message = "No changes were made to the JS function.";
                $messageType = "info";
            }
        } catch (Exception $e) {
            $message = "Error updating JS function: " . $e->getMessage();
            $messageType = "danger";
        }
    } elseif (isset($_POST['get_function_content'])) {
        try {
            $functionName = $_POST['function_name'];
            $content = $jsManager->getFunctionContent($functionName);
            echo $content;
            exit;
        } catch (Exception $e) {
            http_response_code(500);
            echo "Error fetching function content: " . $e->getMessage();
            exit;
        }
    }
}

$allFunctions = $jsManager->getAllJSFunctions();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>JS Manager</title>
    <link href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <h1>JS Manager</h1>
        
        <?php if ($message): ?>
            <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show" role="alert">
                <?php echo $message; ?>
                <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
        <?php endif; ?>
        
        <h2 class="mt-4">All JS Functions</h2>
        <form method="post" id="jsForm">
            <div class="form-group">
                <label for="function_name">Function Name</label>
                <select class="form-control" id="function_name" name="function_name">
                    <?php foreach ($allFunctions as $function): ?>
                        <option value="<?php echo htmlspecialchars($function); ?>" <?php echo (isset($_SESSION['current_function']) && $_SESSION['current_function'] === $function) ? 'selected' : ''; ?>><?php echo htmlspecialchars($function); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="function_content">Function Content</label>
                <textarea class="form-control" id="function_content" name="function_content" rows="10"></textarea>
            </div>
            <button type="submit" name="update_function" class="btn btn-primary">Update Function</button>
        </form>
    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    <script>
        $(document).ready(function() {
            const functionName = $('#function_name');
            const functionContent = $('#function_content');

            function updateFunctionContent() {
                $.ajax({
                    url: 'js_manager.php',
                    method: 'POST',
                    data: { 
                        get_function_content: true,
                        function_name: functionName.val() 
                    },
                    success: function(response) {
                        functionContent.val(response);
                    },
                    error: function(xhr, status, error) {
                        alert('Error fetching function content: ' + xhr.responseText);
                    }
                });
            }

            functionName.on('change', updateFunctionContent);
            updateFunctionContent(); // Initial load

            // Prevent form submission if no changes were made
            $('#jsForm').on('submit', function(e) {
                if (functionContent.val().trim() === '') {
                    e.preventDefault();
                    alert('Function content cannot be empty.');
                }
            });
        });
    </script>
</body>
</html>
