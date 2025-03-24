/**
 * REDAXO Media Manager Helper - SRCSET
 * Dynamic srcset handling
 * 
 * @author KLXM Crossmedia GmbH
 * @version 1.0
 */
(function() {
    'use strict';
    
    /**
     * Process all images with data-srcset attribute
     */
    function processSrcset() {
        var images = document.querySelectorAll('img[data-srcset]');
        
        for (var i = 0; i < images.length; i++) {
            var img = images[i];
            var currentWidth = img.clientWidth || img.parentElement.clientWidth || 0;
            var srcsetVal = img.getAttribute('data-srcset');
            
            // Parse srcset attribute
            var srcset = parseSrcset(srcsetVal);
            
            // Find best matching source
            var bestMatch = findBestMatch(srcset, currentWidth);
            
            if (bestMatch) {
                img.setAttribute('src', bestMatch);
            }
        }
    }
    
    /**
     * Parse srcset string to array of objects
     * 
     * @param {string} srcset - The srcset attribute string
     * @return {Array} Array of objects with url and width
     */
    function parseSrcset(srcset) {
        var sources = [];
        var items = srcset.split(',');
        
        for (var i = 0; i < items.length; i++) {
            var item = items[i].trim();
            var parts = item.split(' ');
            
            if (parts.length >= 2) {
                var url = parts[0];
                var descriptor = parts[1];
                var width = parseInt(descriptor);
                
                if (!isNaN(width)) {
                    sources.push({
                        url: url,
                        width: width
                    });
                }
            }
        }
        
        // Sort by width
        sources.sort(function(a, b) {
            return a.width - b.width;
        });
        
        return sources;
    }
    
    /**
     * Find best matching source for current width
     * 
     * @param {Array} sources - Array of source objects
     * @param {number} currentWidth - Current element width
     * @return {string|null} URL of best matching source or null
     */
    function findBestMatch(sources, currentWidth) {
        // If no sources, return null
        if (sources.length === 0 || currentWidth === 0) {
            return null;
        }
        
        // If width is smaller than smallest source, use smallest
        if (currentWidth <= sources[0].width) {
            return sources[0].url;
        }
        
        // If width is larger than largest source, use largest
        if (currentWidth >= sources[sources.length - 1].width) {
            return sources[sources.length - 1].url;
        }
        
        // Find best matching source
        for (var i = 0; i < sources.length - 1; i++) {
            if (currentWidth >= sources[i].width && currentWidth < sources[i + 1].width) {
                // If closer to next size, use next size
                if (currentWidth - sources[i].width > sources[i + 1].width - currentWidth) {
                    return sources[i + 1].url;
                }
                // Otherwise use current size
                return sources[i].url;
            }
        }
        
        // Fallback to last source
        return sources[sources.length - 1].url;
    }
    
    /**
     * Initialize after DOM is ready
     */
    function init() {
        // Process images initially
        processSrcset();
        
        // Process on load to handle all images
        window.addEventListener('load', processSrcset);
        
        // Process images on window resize (with debounce)
        var resizeTimer;
        window.addEventListener('resize', function() {
            clearTimeout(resizeTimer);
            resizeTimer = setTimeout(processSrcset, 100);
        });
    }
    
    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
    
    // Make processSrcset available globally
    window.klxmMediaSrcsetProcess = processSrcset;
})();
