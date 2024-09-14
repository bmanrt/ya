<?php
class IndexTextManager {
    private $htmlFile = 'index.html';

    public function __construct() {
        if (!file_exists($this->htmlFile)) {
            throw new Exception("HTML file not found: {$this->htmlFile}");
        }
    }

    public function getHTMLContent() {
        return file_get_contents($this->htmlFile);
    }

    public function updateHTML($newContent) {
        if (file_put_contents($this->htmlFile, $newContent) === false) {
            throw new Exception("Failed to update HTML file");
        }
    }

    public function getAllTextContent() {
        $html = $this->getHTMLContent();
        $dom = new DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML(mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8'), LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        $xpath = new DOMXPath($dom);

        $textNodes = $xpath->query('//text()[normalize-space()]');
        $textContent = [];

        foreach ($textNodes as $node) {
            $parentNode = $node->parentNode;
            $xpath = $this->getXPath($parentNode);
            $textContent[$xpath] = trim($node->nodeValue);
        }

        return $textContent;
    }

    private function getXPath($node) {
        $path = '';
        while ($node !== null && $node->nodeType === XML_ELEMENT_NODE) {
            $currentPath = $node->nodeName;
            if ($node->hasAttributes()) {
                foreach ($node->attributes as $attr) {
                    if ($attr->name === 'id') {
                        $currentPath .= "[@id='" . addslashes($attr->value) . "']";
                        break;
                    } elseif ($attr->name === 'class') {
                        $currentPath .= "[@class='" . addslashes($attr->value) . "']";
                        break;
                    }
                }
            }
            $path = '/' . $currentPath . $path;
            $node = $node->parentNode;
        }
        return $path;
    }

    public function updateTextContent($xpath, $newText) {
        $html = $this->getHTMLContent();
        $dom = new DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML(mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8'), LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        $domXpath = new DOMXPath($dom);

        try {
            $nodes = $domXpath->query($xpath);
            if ($nodes && $nodes->length > 0) {
                $nodes->item(0)->nodeValue = htmlspecialchars($newText, ENT_QUOTES, 'UTF-8');
                $updatedHtml = $dom->saveHTML();
                $this->updateHTML($updatedHtml);
                return true;
            }
        } catch (Exception $e) {
            error_log("XPath query error: " . $e->getMessage());
        }
        return false;
    }
}

$indexTextManager = new IndexTextManager();

$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_text'])) {
        try {
            $xpath = $_POST['xpath'];
            $newText = $_POST['new_text'];
            if ($indexTextManager->updateTextContent($xpath, $newText)) {
                $message = "Text updated successfully.";
                $messageType = "success";
            } else {
                $message = "Failed to update text. XPath not found or invalid.";
                $messageType = "danger";
            }
        } catch (Exception $e) {
            $message = "Error: " . $e->getMessage();
            $messageType = "danger";
        }
    }
}

$allTextContent = $indexTextManager->getAllTextContent();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Index Text Manager</title>
    <link href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <h1>Index Text Manager</h1>

        <?php if ($message): ?>
            <div class="alert alert-<?php echo htmlspecialchars($messageType); ?> alert-dismissible fade show" role="alert">
                <?php echo htmlspecialchars($message); ?>
                <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
        <?php endif; ?>

        <form method="post">
            <div class="form-group">
                <label for="xpath">Select Text to Edit:</label>
                <select class="form-control" id="xpath" name="xpath">
                    <?php foreach ($allTextContent as $xpath => $text): ?>
                        <option value="<?php echo htmlspecialchars($xpath, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars(substr($text, 0, 50), ENT_QUOTES, 'UTF-8') . '...'; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="new_text">New Text:</label>
                <textarea class="form-control" id="new_text" name="new_text" rows="3"></textarea>
            </div>
            <button type="submit" name="update_text" class="btn btn-primary">Update Text</button>
        </form>
    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    <script>
        $(document).ready(function() {
            $('#xpath').on('change', function() {
                var selectedXPath = $(this).val();
                var selectedText = <?php echo json_encode($allTextContent, JSON_HEX_APOS | JSON_HEX_QUOT); ?>[selectedXPath];
                $('#new_text').val(selectedText);
            });

            // Trigger change event on page load to populate the textarea
            $('#xpath').trigger('change');
        });
    </script>
</body>
</html>
