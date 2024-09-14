<?php
class CSSManager {
    private $cssFile = 'assets/css/main.css';

    public function __construct() {
        if (!file_exists($this->cssFile)) {
            throw new Exception("CSS file not found: {$this->cssFile}");
        }
    }

    public function getCSSContent() {
        return file_get_contents($this->cssFile);
    }

    public function updateCSS($newContent) {
        if (file_put_contents($this->cssFile, $newContent) === false) {
            throw new Exception("Failed to update CSS file");
        }
    }

    public function getAllCSSRules() {
        $css = $this->getCSSContent();
        preg_match_all('/([^{]+)\s*{\s*([^}]+)\s*}/', $css, $matches, PREG_SET_ORDER);
        $rules = [];
        foreach ($matches as $match) {
            $selector = trim($match[1]);
            $properties = explode(';', trim($match[2]));
            $rules[$selector] = [];
            foreach ($properties as $property) {
                $parts = explode(':', $property, 2);
                if (count($parts) == 2) {
                    $rules[$selector][trim($parts[0])] = trim($parts[1]);
                }
            }
        }
        return $rules;
    }

    public function updateRule($selector, $properties) {
        $css = $this->getCSSContent();
        $updatedRule = $selector . " {\n";
        foreach ($properties as $property => $value) {
            $updatedRule .= "    " . $property . ": " . $value . ";\n";
        }
        $updatedRule .= "}";
        
        $pattern = '/' . preg_quote($selector, '/') . '\s*{[^}]*}/';
        $css = preg_replace($pattern, $updatedRule, $css);
        $this->updateCSS($css);
        return true;
    }
}

$cssManager = new CSSManager();

$message = '';
$messageType = '';

// Initialize session
session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_rule'])) {
        try {
            $selector = $_POST['selector'];
            $properties = $_POST['properties'];
            if ($cssManager->updateRule($selector, $properties)) {
                $message = "CSS rule updated successfully.";
                $messageType = "success";
                // Store the current selector in session
                $_SESSION['current_selector'] = $selector;
            } else {
                $message = "No changes were made to the CSS rule.";
                $messageType = "info";
            }
        } catch (Exception $e) {
            $message = "Error updating CSS rule: " . $e->getMessage();
            $messageType = "danger";
        }
    }
}

$allRules = $cssManager->getAllCSSRules();

// Define common CSS properties and their possible values
$commonProperties = [
    'color' => ['#000000', '#FFFFFF', '#FF0000', '#00FF00', '#0000FF', 'inherit'],
    'background-color' => ['#FFFFFF', '#000000', '#F0F0F0', '#CCCCCC', 'transparent'],
    'font-size' => ['12px', '14px', '16px', '18px', '20px', '24px', '1em', '1.2em', '1.5em'],
    'font-weight' => ['normal', 'bold', '100', '200', '300', '400', '500', '600', '700', '800', '900'],
    'text-align' => ['left', 'center', 'right', 'justify'],
    'margin' => ['0', '5px', '10px', '15px', '20px', 'auto'],
    'padding' => ['0', '5px', '10px', '15px', '20px'],
    'border' => ['none', '1px solid black', '2px dashed red', '3px dotted blue'],
    'display' => ['block', 'inline', 'inline-block', 'flex', 'grid', 'none'],
    'position' => ['static', 'relative', 'absolute', 'fixed', 'sticky']
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CSS Manager</title>
    <link href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <h1>CSS Manager</h1>
        
        <?php if ($message): ?>
            <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show" role="alert">
                <?php echo $message; ?>
                <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
        <?php endif; ?>
        
        <h2 class="mt-4">All CSS Rules</h2>
        <form method="post" id="cssForm">
            <div class="form-group">
                <label for="selector">Selector</label>
                <select class="form-control" id="selector" name="selector">
                    <?php foreach (array_keys($allRules) as $selector): ?>
                        <option value="<?php echo htmlspecialchars($selector); ?>" <?php echo (isset($_SESSION['current_selector']) && $_SESSION['current_selector'] === $selector) ? 'selected' : ''; ?>><?php echo htmlspecialchars($selector); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div id="properties">
                <!-- Properties will be dynamically loaded here -->
            </div>
            <button type="submit" name="update_rule" class="btn btn-primary">Update Rule</button>
        </form>
    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    <script>
        $(document).ready(function() {
            const allRules = <?php echo json_encode($allRules); ?>;
            const commonProperties = <?php echo json_encode($commonProperties); ?>;
            const selector = $('#selector');
            const propertiesDiv = $('#properties');

            function updateProperties() {
                const selectedSelector = selector.val();
                const properties = allRules[selectedSelector];
                propertiesDiv.empty();
                $.each(properties, function(property, value) {
                    let input;
                    if (commonProperties[property]) {
                        input = $('<select>', {
                            class: 'form-control',
                            name: 'properties[' + property + ']'
                        });
                        $.each(commonProperties[property], function(i, option) {
                            input.append($('<option>', {
                                value: option,
                                text: option,
                                selected: option === value
                            }));
                        });
                        if (!commonProperties[property].includes(value)) {
                            input.append($('<option>', {
                                value: value,
                                text: 'Custom: ' + value,
                                selected: true
                            }));
                        }
                    } else {
                        input = $('<input>', {
                            type: 'text',
                            class: 'form-control',
                            name: 'properties[' + property + ']',
                            value: value
                        });
                    }
                    propertiesDiv.append(
                        $('<div>', { class: 'form-group' }).append(
                            $('<label>').text(property),
                            input
                        )
                    );
                });
            }

            selector.on('change', updateProperties);
            updateProperties(); // Initial load

            // Prevent form submission if no changes were made
            $('#cssForm').on('submit', function(e) {
                let hasChanges = false;
                $('#properties select, #properties input').each(function() {
                    if ($(this).val() !== allRules[selector.val()][$(this).attr('name').match(/\[(.*?)\]/)[1]]) {
                        hasChanges = true;
                        return false; // Break the loop
                    }
                });
                if (!hasChanges) {
                    e.preventDefault();
                    alert('No changes were made to the CSS rule.');
                }
            });
        });
    </script>
</body>
</html>
