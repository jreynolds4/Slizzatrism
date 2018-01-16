<?php
namespace Sitecake;

use pclzip\pclzip as pclzip;
use ZipArchive as ZipArchive;

class upgrade
{
    public static function perform()
    {
        $latest = upgrade::latest_remote();
        $current = upgrade::latest_local();

        return ($latest > $current) ?
            upgrade::upgrade_to(upgrade::to_version($latest)) :
            ['status' => 0, 'upgrade' => 0];
    }

    public static function latest_remote()
    {
        $resp = client::get('http://sitecake.com/dl/upgrade/latest.txt');
        if ($resp->isSuccess()) {
            return upgrade::version($resp->getBody());
        } else {
            return -1;
        }
    }

    public static function version($str)
    {
        if (preg_match('/([0-9]+)\.([0-9]+)\.([0-9]+)/', trim($str), $matches) >
            0
        ) {
            return $matches[1] * 1000000 + $matches[2] * 1000 + $matches[3];
        } else {
            return -1;
        }
    }

    public static function latest_local()
    {
        $versions = io::glob(SC_ROOT . '/' . 'sitecake' . '/' .
                             '*.*.*', GLOB_ONLYDIR);

        return array_reduce($versions, function ($latest, $item) {
            $curr = upgrade::version($item);

            return ($curr > $latest) ? $curr : $latest;
        }, -1);
    }

    public static function upgrade_to($ver)
    {
        $file = upgrade::download($ver);
        if (is_array($file)) {
            $res = $file;
        } else {
            $res = upgrade::extract($ver, $file);
            io::unlink($file);
        }
        if ($res['status'] == 0) {
            upgrade::switch_to($ver);
        }

        return $res;
    }

    public static function download($ver)
    {
        $url = 'http://sitecake.com/dl/upgrade/sitecake-' .
               $ver . '-upgrade.zip';
        $resp = client::get($url);
        if ($resp->isSuccess()) {
            $file = TEMP_DIR . '/' . 'sitecake-' . $ver . '-upgrade.zip';
            io::file_put_contents($file, $resp->getBody());

            return $file;
        } else {
            return [
                'status' => -1,
                'errorMessage' => 'Unable to download upgrade from ' . $url
            ];
        }
    }

    public static function extract($ver, $file)
    {
        $dir = SC_ROOT . '/' . 'sitecake';
        if (class_exists('ZipArchive')) {
            $res = upgrade::extract_ziparchive($file, $dir);
        } else {
            $res = pclzip::extract($file, $dir);
        }

        return $res ?
            ['status' => 0, 'upgrade' => 1, 'latest' => $ver] :
            [
                'status' => -1,
                'errorMessage' => 'Unable to extract the upgrade archive'
            ];
    }

    public static function extract_ziparchive($zipfile, $dest)
    {
        $z = new ZipArchive();
        if ($z->open($zipfile) === true) {
            return $z->extractTo($dest);
        } else {
            return false;
        }
    }

    public static function switch_to($ver)
    {
        io::file_put_contents(
            SC_ROOT . '/' . 'sitecake.php',
            "<?php include 'sitecake/$ver/server/admin.php';");
    }

    public static function to_version($num)
    {
        $major = floor($num / 1000000);
        $minor = floor(($num - $major * 1000000) / 1000);
        $rev = $num - $major * 1000000 - $minor * 1000;

        return "$major.$minor.$rev";
    }
}
