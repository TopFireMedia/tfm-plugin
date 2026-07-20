<?php
/**
 * Plugin Update Checker Library 4.13
 * http://w-shadow.com/
 * 
 * Copyright 2022 Janis Elsts
 * Released under the MIT license. See license.txt for details.
 */

if (!class_exists('Puc_v4_Factory')) {
    class Puc_v4_Factory {
        protected static $classVersion = '4.13';
        protected static $minPhpVersion = '5.3';
        protected static $checkHost = true;
        protected static $useMinifiedBuild = true;

        /**
         * Create a new instance of the update checker.
         *
         * @param string $metadataUrl The URL of the metadata file.
         * @param string $fullPath The full path to the main plugin file.
         * @param string $slug The plugin slug.
         * @param int $checkPeriod How often to check for updates (in hours).
         * @param string $optionName Where to store book-keeping info about update checks.
         * @return Puc_v4p13_Plugin_UpdateChecker
         */
        public static function buildUpdateChecker($metadataUrl, $fullPath, $slug = '', $checkPeriod = 12, $optionName = '') {
            if (self::shouldUseMinifiedBuild()) {
                require_once dirname(__FILE__) . '/puc-v4p13-factory.minified.php';
            } else {
                require_once dirname(__FILE__) . '/puc-v4p13-factory.php';
            }
            return new Puc_v4p13_Plugin_UpdateChecker($metadataUrl, $fullPath, $slug, $checkPeriod, $optionName);
        }

        /**
         * Check if the current PHP version is compatible with the library.
         *
         * @return bool
         */
        public static function isPhpVersionCompatible() {
            return version_compare(PHP_VERSION, self::$minPhpVersion, '>=');
        }

        /**
         * Check if we should use the minified build.
         *
         * @return bool
         */
        protected static function shouldUseMinifiedBuild() {
            return self::$useMinifiedBuild;
        }

        /**
         * Get the library version.
         *
         * @return string
         */
        public static function getVersion() {
            return self::$classVersion;
        }
    }
}

// Initialize the factory
if (!function_exists('puc_v4_Factory')) {
    function puc_v4_Factory() {
        return Puc_v4_Factory::class;
    }
} 