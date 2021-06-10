<?php

class DebugBarPlugin extends Plugin
{
    public function afterSiteLoad()
    {
        register_shutdown_function([(new DebugBarPlugin()), 'renderDebugBar']);
    }

    public function afterAdminLoad()
    {
        register_shutdown_function([(new DebugBarPlugin()), 'renderDebugBar']);
    }

    public function benchmark(): string
    {
        $timeTaken = round((microtime(true) - $_SERVER["REQUEST_TIME_FLOAT"]), 3) . 's';
        $memoryUsed = round((memory_get_usage() / 1048576), 2)  . ' MB';
        return "Time : $timeTaken | Mem Use: $memoryUsed";
    }

    /**
     * (GitHub) Copy this read-only report when creating a bug report for faster resolution
     */
    public function form()
    {
        $report = $this->getDebugReport();
        echo '<h3>Report</h3>';
        echo '<textarea readonly style="height: auto" rows=25>';
        echo json_encode($report, JSON_PRETTY_PRINT);
        echo '</textarea>';
    }

    public function getDebugReport(): array
    {
        global $site, $pluginsInstalled;

        // Remove private information from report
        $bluditActivePlugins = [];
        if (!empty($pluginsInstalled)) {
            foreach ($pluginsInstalled as $pluginName => $pluginObject) {
                $bluditActivePlugins[$pluginName]['metadata'] = $pluginObject->metadata;
            }
        }

        $serverInfo = $_SERVER;
        // Remove private cookie from report
        if (isset($serverInfo['HTTP_COOKIE'])) {
            $serverInfo['HTTP_COOKIE'] = '';
        }
        return [
            'bluditVersion'          => BLUDIT_VERSION,
            'bluditInstallationSize' => Filesystem::bytesToHumanFileSize(Filesystem::getSize(PATH_ROOT)),
            'phpVersion'             => phpversion(),
            'phpUploadMaxFilesize'   => ini_get('upload_max_filesize'),
            'phpPostMaxSize'         => ini_get('post_max_size'),
            'phpUploadTempDir'       => ini_get('upload_tmp_dir'),
            'phpSessionLifetime'     => ini_get('session.gc_maxlifetime'),
            'bluditActiveTheme'      => $site->theme(),
            'bluditActivePlugins'    => $bluditActivePlugins,
            'phpExtensions'          => get_loaded_extensions(),
            'siteDb'                 => $site->db,
            'phpServerInfo'          => $serverInfo,
            // Get user constants only
            'phpConstants'           => get_defined_constants(true)["user"]
        ];
    }

    public function renderDebugBar()
    {
        $includedFiles = array_map(function ($file) {
            // Strip application path from filenames
            return Text::replace(PATH_ROOT, '', $file);
        }, get_included_files());
        $includedFilesCount = count($includedFiles);
        $includedFilesHtml = '<table id="includedFilesTable">';
        foreach ($includedFiles as $file) {
            $includedFilesHtml .= "<tr><td>$file</td></tr>";
        }
        $includedFilesHtml .= '</table>';

        // Library: https://sweetalert2.github.io/
        echo "
<script src='https://cdn.jsdelivr.net/npm/sweetalert2@10.10.4/dist/sweetalert2.all.min.js' integrity='sha256-ywfdragt7Ymli3R5hoNqyxBQ/1/2fHT2NdBRdeOwi7U=' crossorigin='anonymous'></script>

<script>
function showDebugBarIncludedFiles()
{
    Swal.fire({
      title: 'Included Files ({$includedFilesCount})',
      html: '{$includedFilesHtml}',
      confirmButtonText: 'Close',
      animation: false
    })
}
</script>

<style>" . file_get_contents(__DIR__ . '/css/plugin.css') . "</style>

<div class='sticky-debug-bar'>
  <a onClick='showDebugBarIncludedFiles()'>Files Included ({$includedFilesCount})</a> " . $this->benchmark() . " | <a href='" . HTML_PATH_ADMIN_ROOT . "developers' target='_blank'>Developers</a>
</div>
";
    }
}
