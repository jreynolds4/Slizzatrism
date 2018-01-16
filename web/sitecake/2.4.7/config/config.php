<?php

# SYSTEM CONFIGURATION (You probably shouldn't change this if you are not sure what you are doing :))
/**
 * Debug mode.
 * This should be set to false on production server
 */
$app['debug'] = false;
/**
 * Entry point file name
 * By default entry point for Sitecake CMS is sitecake.php file in site root directory.
 * If you need to change filename of this file also change it  here in configuration
 */
$app['entry_point_file_name'] = 'sitecake.php';
/**
 * Session handler.
 * By default 'files' session handler is used. This value can also be set to 'memcache', 'memcached' or 'redis'
 * if your environment support these options. If neither of values above is working, set this value to null and let
 * Sitecake to try to figure things out
 */

# SESSION CONFIGURATION SECTION
$app['session.save_handler'] = 'files';
/**
 * Options for selected session handler.
 * For native session handlers valid storage options that can be set are :
 *      cache_limiter, "nocache" (use "0" to prevent headers from being sent entirely).
 *      cookie_domain, ""
 *      cookie_httponly, ""
 *      cookie_lifetime, "0"
 *      cookie_path, "/"
 *      cookie_secure, ""
 *      entropy_file, ""
 *      entropy_length, "0"
 *      gc_divisor, "100"
 *      gc_maxlifetime, "1440"
 *      gc_probability, "1"
 *      hash_bits_per_character, "4"
 *      hash_function, "0"
 *      name, "PHPSESSID"
 *      referer_check, ""
 *      serialize_handler, "php"
 *      use_cookies, "1"
 *      use_only_cookies, "1"
 *      use_trans_sid, "0"
 *      upload_progress.enabled, "1"
 *      upload_progress.cleanup, "1"
 *      upload_progress.prefix, "upload_progress_"
 *      upload_progress.name, "PHP_SESSION_UPLOAD_PROGRESS"
 *      upload_progress.freq, "1%"
 *      upload_progress.min-freq, "1"
 *      url_rewriter.tags, "a=href,area=href,frame=src,form=,fieldset="
 *      save_path, ""
 * For 'memcache' and 'memcached' handlers additional valid options are:
 *      prefix, "sc"
 *      expiretime, 86400 (24h)
 *      servers, [['127.0.0.1', 11211]]
 * For 'redis' handler additional valid options are:
 *      prefix, "sc"
 *      expiretime, 86400 (24h)
 *      server, ['127.0.0.1', 6379]
 */
$app['session.options'] = [];
/**
 * Example configuration for memcache session storage
 */
//$app['session.save_handler'] = 'memcache';
//$app['session.options'] = [
//  'servers' => [['127.0.0.1', 11211]]
//];
/**
 * Example configuration for redis session storage
 */
//$app['session.save_handler'] = 'redis';
//$app['session.options'] = [
//  'server' => ['127.0.0.1', 6379]
//];


# FILESYSTEM CONFIGURATION SECTION
/**
 * If the PHP process on the server has permission to write to the website
 * root files (e.g. to delete/update html files, to create and delete folders)
 * use 'local' filesystem adapter.
 */
$app['filesystem.adapter'] = 'local';
/**
 * If the PHP process on the server doesn't have permission to write to the website
 * root files then use the 'ftp' adapter and provide necessary FTP access properties.
 * FTP protocol will be used to manage the website root files.
 */
//$app['filesystem.adapter'] = 'ftp';
// optional ftp adapter config settings
//$app['filesystem.adapter.config'] = [
//    'root' => '/path/to/root',
//    'passive' => true,
//    'ssl' => true,
//    'timeout' => 30,
//    'host' => 'ftp.example.com',
//    'username' => 'username',
//    'password' => 'password',
//    'port' => 21
//];

# LOG CONFIGURATION SECTION
/**
 * File size that certain log file can reach before it is archived and new log file is created
 */
$app['log.size'] = '2MB';
/**
 * The number of the recent log archives to be kept
 */
$app['log.archive_size'] = 5;
/**
 * Uncomment to define specific path to log file. Otherwise default path will be used.
 * Should be relative path to sitecake.php file
 */
//$app['log.path'] = 'path/to/your/log/file';

# ERROR CONFIGURATION SECTION
/**
 * Error reporting level
 */
$app['error.level'] = E_ALL & ~E_DEPRECATED & ~E_STRICT;

# SITE CONFIGURATION SECTION
/**
 * The number of the recent site versions to be kept in backup
 */
$app['site.number_of_backups'] = 2;
/**
 * Default home page names
 */
$app['site.default_pages'] = ['index.html', 'index.htm', 'index.php', 'index.shtml', 'index.php5'];

# IMAGE MANIPULATION CONFIGURATION SECTION
/**
 * List of image widths in pixels that would be used for generating
 * images for srcset attribute.
 * @see http://w3c.github.io/html/semantics-embedded-content.html#element-attrdef-img-srcset
 */
$app['image.srcset_widths'] = [1280, 960, 640, 320];
/**
 * Max relative diff (in percents) between two image widths in pixels
 * so they could be considered similar
 */
$app['image.srcset_width_maxdiff'] = 20;

# CONTENT CONFIGURATION SECTION
/**
 * Indentation to use when updating content containers
 */
$app['content.indent'] = '    ';

# PAGES CONFIGURATION SECTION
/**
 * If this is set to TRUE, in case when page is modified through editor and content is not published, but same page is
 * also uploaded manually, SiteCake will take uploaded page's content as valid one.
 * If this is set to FALSE, manual changes will be disregarded.
 */
$app['pages.prioritize_manual_changes'] = true;
/**
 * Indicates weather generated pages should be linked relatively to document root or site root
 * If this option is set to TRUE, document relative paths will be used for navigation links href value.
 * Otherwise site relative paths would be used.
 */
$app['pages.use_document_relative_paths'] = false;
/**
 * Indicates weather default page name should be used when building navigation url or not
 * e.g. if this is set to true, sitecake will use /about/index.html instead of /about in urls by default
 */
$app['pages.use_default_page_name_in_url'] = false;
/**
 * The main navigation item template
 */
$app['pages.nav.item_template'] = '<li><a class="${active}" href="${url}" title="${titleText}">${title}</a></li>';
/**
 * An alternative nav configuration example, without using <ul> tag as the container and <li> tags for menu items
 */
//$app['pages.nav.item_template'] = '<li><a accesskey="${order}" href="${url}">${title}</a> <em>(${order})</em></li>';
/**
 * CSS class to be used for active menu link. If set to false, no class will be used
 * In a dynamic site where menu is defined in include file this will probably not be applicable
 */
$app['pages.nav.active_class'] = 'active';
