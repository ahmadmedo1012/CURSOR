<?php

/**
 * Console Error Scanner for GameBox
 * PHP-based static analysis scanner
 */

class ConsoleScanner
{
    private $results = [];
    private $outputDir;
    private $reportJson;
    private $reportMd;

    private $pagesToScan = [
        ['name' => 'Home', 'url' => '/', 'file' => 'index.php'],
        ['name' => 'Login', 'url' => '/auth/login.php', 'file' => 'auth/login.php'],
        ['name' => 'Register', 'url' => '/auth/register.php', 'file' => 'auth/register.php'],
        ['name' => 'Catalog', 'url' => '/catalog.php', 'file' => 'catalog.php'],
        ['name' => 'Orders', 'url' => '/account/orders.php', 'file' => 'account/orders.php'],
        ['name' => 'Profile', 'url' => '/account/profile.php', 'file' => 'account/profile.php'],
        ['name' => 'Admin Dashboard', 'url' => '/admin/dashboard.php', 'file' => 'admin/dashboard.php'],
        ['name' => 'Admin Login', 'url' => '/admin/login.php', 'file' => 'admin/login.php'],
        ['name' => 'Wallet', 'url' => '/wallet/', 'file' => 'wallet/index.php'],
        ['name' => 'Leaderboard', 'url' => '/leaderboard.php', 'file' => 'leaderboard.php']
    ];

    public function __construct()
    {
        $this->outputDir = __DIR__ . '/../tmp';
        $this->reportJson = $this->outputDir . '/console_report.json';
        $this->reportMd = $this->outputDir . '/console_report.md';

        $this->results = [
            'timestamp' => date('c'),
            'pages' => [],
            'summary' => [
                'totalPages' => 0,
                'pagesWithErrors' => 0,
                'totalErrors' => 0,
                'totalWarnings' => 0,
                'errorTypes' => [],
                'potentialIssues' => []
            ]
        ];
    }

    public function scan()
    {
        echo "🔍 Starting console error scan...\n";

        // Ensure output directory exists
        if (!is_dir($this->outputDir)) {
            mkdir($this->outputDir, 0755, true);
        }

        foreach ($this->pagesToScan as $page) {
            $this->scanPage($page);
        }

        $this->generateReports();
        echo "✅ Scan completed successfully!\n";
    }

    private function scanPage($pageInfo)
    {
        echo "📄 Scanning: {$pageInfo['name']} ({$pageInfo['file']})\n";

        $pageResult = [
            'name' => $pageInfo['name'],
            'url' => $pageInfo['url'],
            'file' => $pageInfo['file'],
            'errors' => [],
            'warnings' => [],
            'potentialIssues' => [],
            'timestamp' => date('c')
        ];

        try {
            $filePath = __DIR__ . '/../' . $pageInfo['file'];

            if (!file_exists($filePath)) {
                $pageResult['errors'][] = [
                    'message' => "File not found: {$pageInfo['file']}",
                    'source' => $pageInfo['file'],
                    'line' => 0,
                    'column' => 0
                ];
            } else {
                $content = file_get_contents($filePath);
                $this->scanJavaScriptIssues($content, $pageResult);
                $this->scanAssetIssues($content, $pageResult);
                $this->scanMimeIssues($content, $pageResult);
            }
        } catch (Exception $e) {
            echo "❌ Error scanning {$pageInfo['name']}: " . $e->getMessage() . "\n";
            $pageResult['errors'][] = [
                'message' => "File read error: " . $e->getMessage(),
                'source' => $pageInfo['file'],
                'line' => 0,
                'column' => 0
            ];
        }

        // Update summary
        $this->results['pages'][] = $pageResult;
        $this->results['summary']['totalPages']++;

        if (count($pageResult['errors']) > 0) {
            $this->results['summary']['pagesWithErrors']++;
        }

        $this->results['summary']['totalErrors'] += count($pageResult['errors']);
        $this->results['summary']['totalWarnings'] += count($pageResult['warnings']);

        // Count error types
        foreach ($pageResult['errors'] as $error) {
            $errorType = $this->categorizeError($error['message']);
            $this->results['summary']['errorTypes'][$errorType] =
                ($this->results['summary']['errorTypes'][$errorType] ?? 0) + 1;
        }

        echo "   " . count($pageResult['errors']) . " errors, " .
            count($pageResult['warnings']) . " warnings, " .
            count($pageResult['potentialIssues']) . " potential issues\n";
    }

    private function scanJavaScriptIssues($content, &$pageResult)
    {
        $lines = explode("\n", $content);

        foreach ($lines as $index => $line) {
            $lineNum = $index + 1;

            // Check for console errors/warnings
            if (strpos($line, 'console.error') !== false || strpos($line, 'console.warn') !== false) {
                $pageResult['warnings'][] = [
                    'message' => "Console error/warning found: " . trim($line),
                    'source' => $pageResult['file'],
                    'line' => $lineNum,
                    'column' => 0
                ];
            }

            // Check for undefined variables
            if (preg_match('/\$(\w+)\s*\(/', $line, $matches)) {
                $varName = $matches[1];
                if (
                    !strpos($content, "$${varName} =") &&
                    !strpos($content, "$${varName}??") &&
                    !strpos($content, "$${varName}??")
                ) {
                    $pageResult['potentialIssues'][] = [
                        'message' => "Potential undefined variable: $${varName}",
                        'source' => $pageResult['file'],
                        'line' => $lineNum,
                        'column' => 0
                    ];
                }
            }

            // Check for script tags without defer
            if (
                strpos($line, '<script') !== false &&
                strpos($line, 'defer') === false &&
                strpos($line, 'async') === false
            ) {
                $pageResult['warnings'][] = [
                    'message' => 'Script tag without defer/async may block rendering',
                    'source' => $pageResult['file'],
                    'line' => $lineNum,
                    'column' => 0
                ];
            }

            // Check for inline event handlers
            if (
                strpos($line, 'onclick=') !== false ||
                strpos($line, 'onload=') !== false ||
                strpos($line, 'onchange=') !== false
            ) {
                $pageResult['warnings'][] = [
                    'message' => 'Inline event handlers found - consider using addEventListener',
                    'source' => $pageResult['file'],
                    'line' => $lineNum,
                    'column' => 0
                ];
            }
        }
    }

    private function scanAssetIssues($content, &$pageResult)
    {
        $lines = explode("\n", $content);

        foreach ($lines as $index => $line) {
            $lineNum = $index + 1;

            // Check for asset URLs
            if (preg_match_all('/src=["\']([^"\']+)["\']|href=["\']([^"\']+)["\']/', $line, $matches, PREG_SET_ORDER)) {
                foreach ($matches as $match) {
                    $url = $match[1] ?: $match[2];

                    // Check for protocol-relative URLs
                    if (strpos($url, '//') === 0) {
                        $pageResult['warnings'][] = [
                            'message' => "Protocol-relative URL found: {$url}",
                            'source' => $pageResult['file'],
                            'line' => $lineNum,
                            'column' => 0
                        ];
                    }

                    // Check for HTTP URLs
                    if (strpos($url, 'http://') !== false && strpos($url, 'localhost') === false) {
                        $pageResult['errors'][] = [
                            'message' => "HTTP URL found (Mixed Content): {$url}",
                            'source' => $pageResult['file'],
                            'line' => $lineNum,
                            'column' => 0
                        ];
                    }

                    // Check for complex relative paths
                    if (strpos($url, '..') !== false && substr_count($url, '..') > 2) {
                        $pageResult['warnings'][] = [
                            'message' => "Complex relative path: {$url}",
                            'source' => $pageResult['file'],
                            'line' => $lineNum,
                            'column' => 0
                        ];
                    }
                }
            }
        }
    }

    private function scanMimeIssues($content, &$pageResult)
    {
        $lines = explode("\n", $content);

        foreach ($lines as $index => $line) {
            $lineNum = $index + 1;

            // Check for script tags with explicit type
            if (strpos($line, '<script') !== false && strpos($line, 'type=') !== false) {
                if (strpos($line, 'type="text/javascript"') !== false) {
                    $pageResult['warnings'][] = [
                        'message' => 'Explicit text/javascript type is unnecessary',
                        'source' => $pageResult['file'],
                        'line' => $lineNum,
                        'column' => 0
                    ];
                }
            }

            // Check for module scripts
            if (strpos($line, '<script') !== false && strpos($line, 'type="module"') !== false) {
                $pageResult['potentialIssues'][] = [
                    'message' => 'ES6 module script found - ensure server supports it',
                    'source' => $pageResult['file'],
                    'line' => $lineNum,
                    'column' => 0
                ];
            }
        }
    }

    private function categorizeError($message)
    {
        if (strpos($message, 'Mixed Content') !== false) return 'Mixed Content';
        if (strpos($message, 'undefined variable') !== false) return 'ReferenceError';
        if (strpos($message, 'HTTP URL') !== false) return 'HTTP URL';
        if (strpos($message, 'Protocol-relative') !== false) return 'Protocol-relative URL';
        if (strpos($message, 'Complex relative path') !== false) return 'Complex Path';
        return 'Other';
    }

    private function generateReports()
    {
        // Generate JSON report
        file_put_contents($this->reportJson, json_encode($this->results, JSON_PRETTY_PRINT));

        // Generate Markdown report
        $mdReport = $this->generateMarkdownReport();
        file_put_contents($this->reportMd, $mdReport);

        echo "📊 Reports generated:\n";
        echo "   JSON: {$this->reportJson}\n";
        echo "   Markdown: {$this->reportMd}\n";
    }

    private function generateMarkdownReport()
    {
        $summary = $this->results['summary'];

        $md = "# Console Error Report (Static Analysis)\n\n";
        $md .= "**Generated:** " . date('Y-m-d H:i:s') . "\n";
        $md .= "**Analysis Type:** Static Code Analysis\n\n";

        // Summary
        $md .= "## 📊 Summary\n\n";
        $md .= "| Metric | Count |\n";
        $md .= "|--------|-------|\n";
        $md .= "| Total Pages Scanned | {$summary['totalPages']} |\n";
        $md .= "| Pages with Errors | {$summary['pagesWithErrors']} |\n";
        $md .= "| **Total Errors** | **{$summary['totalErrors']}** |\n";
        $md .= "| Total Warnings | {$summary['totalWarnings']} |\n";

        $totalPotentialIssues = array_sum(array_map(function ($page) {
            return count($page['potentialIssues']);
        }, $this->results['pages']));

        $md .= "| Potential Issues | {$totalPotentialIssues} |\n\n";

        // Error Types
        if (!empty($summary['errorTypes'])) {
            $md .= "## 🚨 Error Types\n\n";
            $md .= "| Type | Count |\n";
            $md .= "|------|-------|\n";

            arsort($summary['errorTypes']);
            foreach ($summary['errorTypes'] as $type => $count) {
                $md .= "| {$type} | {$count} |\n";
            }
            $md .= "\n";
        }

        // Detailed Results
        $md .= "## 📄 Page Details\n\n";

        foreach ($this->results['pages'] as $page) {
            $md .= "### {$page['name']} ({$page['url']})\n\n";
            $md .= "- **File:** {$page['file']}\n";
            $md .= "- **Errors:** " . count($page['errors']) . "\n";
            $md .= "- **Warnings:** " . count($page['warnings']) . "\n";
            $md .= "- **Potential Issues:** " . count($page['potentialIssues']) . "\n\n";

            if (!empty($page['errors'])) {
                $md .= "#### 🚨 Errors\n\n";
                foreach ($page['errors'] as $error) {
                    $md .= "- **Line {$error['line']}** - {$error['message']}\n";
                }
                $md .= "\n";
            }

            if (!empty($page['warnings'])) {
                $md .= "#### ⚠️ Warnings\n\n";
                foreach ($page['warnings'] as $warning) {
                    $md .= "- **Line {$warning['line']}** - {$warning['message']}\n";
                }
                $md .= "\n";
            }

            if (!empty($page['potentialIssues'])) {
                $md .= "#### 🔍 Potential Issues\n\n";
                foreach ($page['potentialIssues'] as $issue) {
                    $md .= "- **Line {$issue['line']}** - {$issue['message']}\n";
                }
                $md .= "\n";
            }
        }

        // Recommendations
        $md .= "## 🔧 Recommendations\n\n";

        if ($summary['totalErrors'] === 0) {
            $md .= "✅ **Excellent!** No critical errors found in static analysis.\n\n";
        } else {
            $md .= "### Priority Fixes:\n\n";

            if (isset($summary['errorTypes']['Mixed Content'])) {
                $md .= "1. **Mixed Content** ({$summary['errorTypes']['Mixed Content']} instances): Replace HTTP URLs with HTTPS\n";
            }

            if (isset($summary['errorTypes']['HTTP URL'])) {
                $md .= "2. **HTTP URLs** ({$summary['errorTypes']['HTTP URL']} instances): Use HTTPS for all external resources\n";
            }

            if (isset($summary['errorTypes']['ReferenceError'])) {
                $md .= "3. **Reference Errors** ({$summary['errorTypes']['ReferenceError']} instances): Check variable definitions\n";
            }

            $md .= "\n";
        }

        if ($summary['totalWarnings'] > 3) {
            $md .= "⚠️ **High Warning Count:** Consider reviewing {$summary['totalWarnings']} warnings\n\n";
        }

        $md .= "---\n";
        $md .= "*Report generated by Static Console Scanner*\n";

        return $md;
    }
}

// Run the scanner
try {
    $scanner = new ConsoleScanner();
    $scanner->scan();

    // Exit with appropriate code
    $summary = $scanner->results['summary'];
    if ($summary['totalErrors'] === 0 && $summary['totalWarnings'] <= 3) {
        echo "🎉 SUCCESS: 0 errors and ≤3 warnings!\n";
        exit(0);
    } else {
        echo "❌ ISSUES FOUND: {$summary['totalErrors']} errors, {$summary['totalWarnings']} warnings\n";
        exit(1);
    }
} catch (Exception $e) {
    echo "💥 Scanner failed: " . $e->getMessage() . "\n";
    exit(1);
}
