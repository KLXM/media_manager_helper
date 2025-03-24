<?php
/**
 * @package redaxo\media-manager-helper
 * @version 1.0
 */

namespace KLXM\MediaManagerHelper;

use rex;
use rex_media_manager;
use rex_sql;

class ResponsiveImage
{
    // Konstanten für Dateitypen, die nicht in srcset verarbeitet werden sollen
    private const NON_PIXEL_FORMATS = ['svg', 'pdf', 'eps'];
    
    /**
     * Überprüft, ob ein Bild ein Nicht-Pixel-Format hat und direkt ausgegeben werden sollte
     *
     * @param string $file Dateiname
     * @return bool True, wenn es ein Nicht-Pixel-Format ist
     */
    public static function isNonPixelFormat(string $file): bool
    {
        $extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        return in_array($extension, self::NON_PIXEL_FORMATS);
    }

    /**
     * Parses a srcset string into an array of widths and descriptors
     *
     * @param string $srcsetString Srcset configuration string
     * @return array<int, string> Array of image widths and descriptors
     */
    public static function parseSrcsetString(string $srcsetString): array
    {
        $srcset = [];
        $items = array_map('trim', explode(',', $srcsetString));
        
        foreach ($items as $item) {
            $parts = array_values(array_filter(explode(' ', $item)));
            if (count($parts) >= 2) {
                $width = (int)$parts[0];
                $descriptor = implode(' ', array_slice($parts, 1));
                $srcset[$width] = $descriptor;
            }
        }
        
        return $srcset;
    }

    /**
     * Get srcset configuration from media type
     *
     * @param string $type Media manager type
     * @return array<int, string> Array of image widths and descriptors
     */
    public static function getSrcsetConfig(string $type): array
    {
        $sql = rex_sql::factory();
        $sql->setQuery('
            SELECT e.parameters
            FROM ' . rex::getTablePrefix() . 'media_manager_type t
            JOIN ' . rex::getTablePrefix() . 'media_manager_type_effect e ON t.id = e.type_id
            WHERE t.name = :type AND e.effect = "srcset_helper"
            ORDER BY e.priority
            LIMIT 1
        ', ['type' => $type]);
        
        if ($sql->getRows() === 0) {
            return [];
        }
        
        $parameters = $sql->getArrayValue('parameters');
        if (!isset($parameters['rex_effect_srcset_helper']['srcset'])) {
            return [];
        }
        
        $srcsetString = $parameters['rex_effect_srcset_helper']['srcset'];
        return self::parseSrcsetString($srcsetString);
    }

    /**
     * Get srcset string for a specific media type and file
     *
     * @param string $type Media manager type
     * @param string $file Image filename
     * @return string srcset attribute content
     */
    public static function getSrcsetString(string $type, string $file): string
    {
        // Get srcset configuration from the media type
        $srcset = self::getSrcsetConfig($type);
        if (empty($srcset)) {
            return '';
        }
        
        $srcsetString = [];
        foreach ($srcset as $width => $descriptor) {
            $srcsetString[] = rex_media_manager::getUrl($type . '__' . $width, $file) . ' ' . $descriptor;
        }
        
        return implode(",\n                ", $srcsetString);
    }

    /**
     * Processes an HTML string to replace srcset attributes
     *
     * @param string $html HTML string
     * @return string Processed HTML
     */
    public static function replaceMediaTags(string $html): string
    {
        // Process img tags with srcset attribute
        $html = self::processImgSrcset($html);
        
        // Process picture tags with srcset attribute
        $html = self::processPictureSrcset($html);

        return $html;
    }

    /**
     * Process img tags with srcset attribute
     *
     * @param string $html HTML content
     * @return string Processed HTML
     */
    protected static function processImgSrcset(string $html): string
    {
        $pattern = '/<img([^>]*?)srcset="rex_media_type=([^"]*?)"([^>]*?)>/i';
        
        return preg_replace_callback($pattern, function($matches) {
            $before = $matches[1];
            $type = $matches[2];
            $after = $matches[3];
            
            // Extract src attribute
            $srcPattern = '/src="([^"]*?)"/i';
            preg_match($srcPattern, $before . $after, $srcMatches);
            
            if (isset($srcMatches[1])) {
                $src = $srcMatches[1];
                // Extract filename from src
                $filePattern = '/rex_media_file=([^&"]*)/i';
                preg_match($filePattern, $src, $fileMatches);
                
                if (isset($fileMatches[1])) {
                    $file = $fileMatches[1];
                    
                    // Bei SVG, PDF, etc. direkt das Original-Bild ausgeben
                    if (self::isNonPixelFormat($file)) {
                        return $matches[0]; // Unverändert zurückgeben
                    }
                    
                    // Get srcset string from media type
                    $srcset = self::getSrcsetString($type, $file);
                    
                    // If no srcset was found, return unchanged
                    if (empty($srcset)) {
                        return $matches[0];
                    }
                    
                    return '<img' . $before . 'srcset="' . $srcset . '"' . $after . '>';
                }
            }
            
            return $matches[0]; // Return unchanged if no match
        }, $html);
    }

    /**
     * Process picture tags with srcset attribute
     *
     * @param string $html HTML content
     * @return string Processed HTML
     */
    protected static function processPictureSrcset(string $html): string
    {
        $pattern = '/<source([^>]*?)srcset="rex_media_type=([^"]*?)"([^>]*?)>/i';
        
        return preg_replace_callback($pattern, function($matches) {
            $before = $matches[1];
            $type = $matches[2];
            $after = $matches[3];
            
            // Try to find file from data-file attribute first
            $fileAttrPattern = '/data-file="([^"]*?)"/i';
            preg_match($fileAttrPattern, $before . $after, $fileAttrMatches);
            
            if (isset($fileAttrMatches[1])) {
                $file = $fileAttrMatches[1];
                
                // Bei SVG, PDF, etc. direkt das Original-Bild ausgeben
                if (self::isNonPixelFormat($file)) {
                    return $matches[0]; // Unverändert zurückgeben
                }
                
                // Get srcset string from media type
                $srcset = self::getSrcsetString($type, $file);
                if (!empty($srcset)) {
                    return '<source' . $before . 'srcset="' . $srcset . '"' . $after . '>';
                }
            }
            
            // Find the img tag within the picture to get the filename
            $picturePattern = '/(<picture[^>]*>)(.*?)(<\/picture>)/is';
            if (preg_match($picturePattern, $html, $pictureMatches)) {
                $pictureContent = $pictureMatches[2];
                $imgPattern = '/<img[^>]*?src="[^"]*?rex_media_file=([^&"]*)/i';
                preg_match($imgPattern, $pictureContent, $imgMatches);
                
                if (isset($imgMatches[1])) {
                    $file = $imgMatches[1];
                    
                    // Bei SVG, PDF, etc. direkt das Original-Bild ausgeben
                    if (self::isNonPixelFormat($file)) {
                        return $matches[0]; // Unverändert zurückgeben
                    }
                    
                    // Get srcset string from media type
                    $srcset = self::getSrcsetString($type, $file);
                    if (!empty($srcset)) {
                        return '<source' . $before . 'srcset="' . $srcset . '"' . $after . '>';
                    }
                }
            }
            
            return $matches[0]; // Return unchanged if no match
        }, $html);
    }

    /**
     * Generiert einen img-Tag mit korrekt konfiguriertem srcset für einen bestimmten Medientyp
     *
     * @param string $file Dateiname des Bildes
     * @param string $type Media Manager Typ
     * @param array<string, string> $attributes Zusätzliche Attribute für den img-Tag
     * @return string HTML img-Tag
     */
    public static function getImageByType(string $file, string $type, array $attributes = []): string
    {
        // Bei SVG, PDF, etc. direkt das Original-Bild ausgeben
        if (self::isNonPixelFormat($file)) {
            $mediaUrl = rex_media_manager::getUrl($type, $file);
            
            $attr = '';
            foreach ($attributes as $name => $value) {
                $attr .= ' ' . $name . '="' . $value . '"';
            }
            
            return '<img src="' . $mediaUrl . '"' . $attr . ' />';
        }
        
        $mediaUrl = rex_media_manager::getUrl($type, $file);
        
        $attr = '';
        foreach ($attributes as $name => $value) {
            $attr .= ' ' . $name . '="' . $value . '"';
        }
        
        return '<img src="' . $mediaUrl . '" srcset="rex_media_type=' . $type . '"' . $attr . ' />';
    }

    /**
     * Generiert einen img-Tag mit srcset-Attribut
     *
     * @param string $file Dateiname des Bildes
     * @param string $type Media Manager Typ
     * @param array<string, string> $attributes Zusätzliche Attribute für den img-Tag
     * @return string HTML img-Tag
     */
    public static function getImgTag(string $file, string $type, array $attributes = []): string
    {
        return self::getImageByType($file, $type, $attributes);
    }

    /**
     * Generiert einen picture-Tag mit srcset-Attributen und Art Direction Support
     *
     * @param string $file Dateiname des Bildes
     * @param string $defaultType Standard Media Manager Typ
     * @param array $sources Array mit Media Queries oder erweiterten Art Direction Konfigurationen
     * @param array<string, string> $imgAttributes Zusätzliche Attribute für den img-Tag
     * @return string HTML picture-Tag
     */
    public static function getPictureTag(string $file, string $defaultType, array $sources = [], array $imgAttributes = []): string
    {
        // Bei SVG, PDF, etc. direkt das Original-Bild ausgeben
        if (self::isNonPixelFormat($file)) {
            return self::getImageByType($file, $defaultType, $imgAttributes);
        }
        
        $mediaUrl = rex_media_manager::getUrl($defaultType, $file);
        
        $imgAttr = '';
        foreach ($imgAttributes as $name => $value) {
            $imgAttr .= ' ' . $name . '="' . $value . '"';
        }
        
        $html = '<picture>';
        
        // Verarbeite die Sources - entweder als einfache Media Queries oder als erweiterte Konfigurationen
        foreach ($sources as $key => $value) {
            // Fall 1: Einfache Media Query => Typ Zuordnung
            if (is_string($key) && is_string($value)) {
                $mediaQuery = $key;
                $type = $value;
                $html .= '<source media="' . $mediaQuery . '" srcset="rex_media_type=' . $type . '" data-file="' . $file . '">';
            }
            // Fall 2: Array mit erweiterter Konfiguration
            elseif (is_array($value)) {
                if (!isset($value['media'], $value['type'])) {
                    continue; // Überspringe ungültige Konfigurationen
                }
                
                $mediaQuery = $value['media'];
                $sourceFile = $value['file'] ?? $file; // Verwende defaultFile wenn nicht spezifiziert
                $type = $value['type'];
                $sizes = $value['sizes'] ?? '';
                
                $sizeAttr = '';
                if (!empty($sizes)) {
                    $sizeAttr = ' sizes="' . $sizes . '"';
                }
                
                $html .= '<source media="' . $mediaQuery . '" srcset="rex_media_type=' . $type . '" data-file="' . $sourceFile . '"' . $sizeAttr . '>';
            }
        }
        
        // Standard-Source hinzufügen
        $html .= '<source srcset="rex_media_type=' . $defaultType . '" data-file="' . $file . '">';
        
        // Img-Tag hinzufügen
        $html .= '<img src="' . $mediaUrl . '"' . $imgAttr . '>';
        
        $html .= '</picture>';
        
        return $html;
    }
}
