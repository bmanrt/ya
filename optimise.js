// Function to load scripts asynchronously
function loadScript(src) {
    const script = document.createElement('script');
    script.src = src;
    script.async = true;
    document.head.appendChild(script);
}

// Load scripts
loadScript('assets/vendor/bootstrap/js/bootstrap.bundle.min.js');
loadScript('assets/js/main.js');

// Lazy load images
document.querySelectorAll('img[loading="lazy"]').forEach(img => {
    img.src = img.dataset.src; // Set the src from data-src
});
