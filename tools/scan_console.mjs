#!/usr/bin/env node
/**
 * Simple Console Error Scanner for GameBox
 * Basic scan without external dependencies
 */

import fs from 'fs/promises';
import path from 'path';
import { fileURLToPath } from 'url';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);

// Configuration
const OUTPUT_DIR = path.join(__dirname, '..', 'tmp');
const REPORT_JSON = path.join(OUTPUT_DIR, 'console_report.json');
const REPORT_MD = path.join(OUTPUT_DIR, 'console_report.md');

// Pages to scan
const PAGES_TO_SCAN = [
    { name: 'Home', url: '/', file: 'index.php' },
    { name: 'Login', url: '/auth/login.php', file: 'auth/login.php' },
    { name: 'Register', url: '/auth/register.php', file: 'auth/register.php' },
    { name: 'Catalog', url: '/catalog.php', file: 'catalog.php' },
    { name: 'Orders', url: '/account/orders.php', file: 'account/orders.php' },
    { name: 'Profile', url: '/account/profile.php', file: 'account/profile.php' },
    { name: 'Admin Dashboard', url: '/admin/dashboard.php', file: 'admin/dashboard.php' },
    { name: 'Admin Login', url: '/admin/login.php', file: 'admin/login.php' },
    { name: 'Wallet', url: '/wallet/', file: 'wallet/index.php' },
    { name: 'Leaderboard', url: '/leaderboard.php', file: 'leaderboard.php' }
];

class StaticScanner {
    constructor() {
        this.results = {
            timestamp: new Date().toISOString(),
            pages: [],
            summary: {
                totalPages: 0,
                pagesWithErrors: 0,
                totalErrors: 0,
                totalWarnings: 0,
                errorTypes: {},
                potentialIssues: []
            }
        };
    }

    async scan() {
        console.log('🔍 Starting static console error scan...');
        
        // Ensure output directory exists
        await fs.mkdir(OUTPUT_DIR, { recursive: true });
        
        for (const page of PAGES_TO_SCAN) {
            await this.scanPage(page);
        }
        
        await this.generateReports();
        console.log('✅ Scan completed successfully!');
    }

    async scanPage(pageInfo) {
        console.log(`📄 Scanning: ${pageInfo.name} (${pageInfo.file})`);
        
        const pageResult = {
            name: pageInfo.name,
            url: pageInfo.url,
            file: pageInfo.file,
            errors: [],
            warnings: [],
            potentialIssues: [],
            timestamp: new Date().toISOString()
        };

        try {
            // Check if file exists
            const filePath = path.join(__dirname, '..', pageInfo.file);
            const fileContent = await fs.readFile(filePath, 'utf8');
            
            // Scan for common JavaScript issues
            await this.scanJavaScriptIssues(fileContent, pageResult);
            
            // Scan for asset path issues
            await this.scanAssetIssues(fileContent, pageResult);
            
            // Scan for MIME type issues
            await this.scanMimeIssues(fileContent, pageResult);
            
        } catch (error) {
            console.error(`❌ Error scanning ${pageInfo.name}:`, error.message);
            pageResult.errors.push({
                message: `File not found or unreadable: ${error.message}`,
                source: pageInfo.file,
                line: 0,
                column: 0
            });
        }

        // Update summary
        this.results.pages.push(pageResult);
        this.results.summary.totalPages++;
        
        if (pageResult.errors.length > 0) {
            this.results.summary.pagesWithErrors++;
        }
        
        this.results.summary.totalErrors += pageResult.errors.length;
        this.results.summary.totalWarnings += pageResult.warnings.length;
        
        // Count error types
        pageResult.errors.forEach(error => {
            const errorType = this.categorizeError(error.message);
            this.results.summary.errorTypes[errorType] = (this.results.summary.errorTypes[errorType] || 0) + 1;
        });
        
        console.log(`   ${pageResult.errors.length} errors, ${pageResult.warnings.length} warnings, ${pageResult.potentialIssues.length} potential issues`);
    }

    async scanJavaScriptIssues(content, pageResult) {
        const lines = content.split('\n');
        
        lines.forEach((line, index) => {
            const lineNum = index + 1;
            
            // Check for common JavaScript errors
            if (line.includes('console.error') || line.includes('console.warn')) {
                pageResult.warnings.push({
                    message: `Console error/warning found: ${line.trim()}`,
                    source: pageResult.file,
                    line: lineNum,
                    column: 0
                });
            }
            
            // Check for undefined variables
            if (line.match(/\$\w+\s*\(/)) {
                // Check if variable is defined
                const varMatch = line.match(/\$(\w+)\s*\(/);
                if (varMatch) {
                    const varName = varMatch[1];
                    if (!content.includes(`$${varName} =`) && !content.includes(`$${varName}??`) && !content.includes(`$${varName}??`)) {
                        pageResult.potentialIssues.push({
                            message: `Potential undefined variable: $${varName}`,
                            source: pageResult.file,
                            line: lineNum,
                            column: 0
                        });
                    }
                }
            }
            
            // Check for missing semicolons in JavaScript
            if (line.includes('<script') && !line.includes('defer') && !line.includes('async')) {
                pageResult.warnings.push({
                    message: 'Script tag without defer/async may block rendering',
                    source: pageResult.file,
                    line: lineNum,
                    column: 0
                });
            }
            
            // Check for inline JavaScript
            if (line.includes('onclick=') || line.includes('onload=') || line.includes('onchange=')) {
                pageResult.warnings.push({
                    message: 'Inline event handlers found - consider using addEventListener',
                    source: pageResult.file,
                    line: lineNum,
                    column: 0
                });
            }
        });
    }

    async scanAssetIssues(content, pageResult) {
        const lines = content.split('\n');
        
        lines.forEach((line, index) => {
            const lineNum = index + 1;
            
            // Check for relative asset paths
            const assetMatches = line.match(/src=["']([^"']+)["']|href=["']([^"']+)["']/g);
            if (assetMatches) {
                assetMatches.forEach(match => {
                    const urlMatch = match.match(/["']([^"']+)["']/);
                    if (urlMatch) {
                        const url = urlMatch[1];
                        
                        // Check for problematic patterns
                        if (url.startsWith('//')) {
                            pageResult.warnings.push({
                                message: `Protocol-relative URL found: ${url}`,
                                source: pageResult.file,
                                line: lineNum,
                                column: 0
                            });
                        }
                        
                        if (url.includes('http://') && !url.includes('localhost')) {
                            pageResult.errors.push({
                                message: `HTTP URL found (Mixed Content): ${url}`,
                                source: pageResult.file,
                                line: lineNum,
                                column: 0
                            });
                        }
                        
                        if (url.includes('..') && url.split('..').length > 2) {
                            pageResult.warnings.push({
                                message: `Complex relative path: ${url}`,
                                source: pageResult.file,
                                line: lineNum,
                                column: 0
                            });
                        }
                    }
                });
            }
        });
    }

    async scanMimeIssues(content, pageResult) {
        const lines = content.split('\n');
        
        lines.forEach((line, index) => {
            const lineNum = index + 1;
            
            // Check for script tags with wrong type
            if (line.includes('<script') && line.includes('type=')) {
                if (line.includes('type="text/javascript"')) {
                    pageResult.warnings.push({
                        message: 'Explicit text/javascript type is unnecessary',
                        source: pageResult.file,
                        line: lineNum,
                        column: 0
                    });
                }
            }
            
            // Check for module scripts
            if (line.includes('<script') && line.includes('type="module"')) {
                pageResult.potentialIssues.push({
                    message: 'ES6 module script found - ensure server supports it',
                    source: pageResult.file,
                    line: lineNum,
                    column: 0
                });
            }
        });
    }

    categorizeError(message) {
        if (message.includes('Mixed Content')) return 'Mixed Content';
        if (message.includes('undefined variable')) return 'ReferenceError';
        if (message.includes('HTTP URL')) return 'HTTP URL';
        if (message.includes('Protocol-relative')) return 'Protocol-relative URL';
        if (message.includes('Complex relative path')) return 'Complex Path';
        return 'Other';
    }

    async generateReports() {
        // Generate JSON report
        await fs.writeFile(REPORT_JSON, JSON.stringify(this.results, null, 2));
        
        // Generate Markdown report
        const mdReport = this.generateMarkdownReport();
        await fs.writeFile(REPORT_MD, mdReport);
        
        console.log(`📊 Reports generated:`);
        console.log(`   JSON: ${REPORT_JSON}`);
        console.log(`   Markdown: ${REPORT_MD}`);
    }

    generateMarkdownReport() {
        const { summary } = this.results;
        
        let md = `# Console Error Report (Static Analysis)\n\n`;
        md += `**Generated:** ${new Date().toLocaleString()}\n`;
        md += `**Analysis Type:** Static Code Analysis\n\n`;
        
        // Summary
        md += `## 📊 Summary\n\n`;
        md += `| Metric | Count |\n`;
        md += `|--------|-------|\n`;
        md += `| Total Pages Scanned | ${summary.totalPages} |\n`;
        md += `| Pages with Errors | ${summary.pagesWithErrors} |\n`;
        md += `| **Total Errors** | **${summary.totalErrors}** |\n`;
        md += `| Total Warnings | ${summary.totalWarnings} |\n`;
        md += `| Potential Issues | ${this.results.pages.reduce((sum, page) => sum + page.potentialIssues.length, 0)} |\n\n`;
        
        // Error Types
        if (Object.keys(summary.errorTypes).length > 0) {
            md += `## 🚨 Error Types\n\n`;
            md += `| Type | Count |\n`;
            md += `|------|-------|\n`;
            Object.entries(summary.errorTypes)
                .sort(([,a], [,b]) => b - a)
                .forEach(([type, count]) => {
                    md += `| ${type} | ${count} |\n`;
                });
            md += `\n`;
        }
        
        // Detailed Results
        md += `## 📄 Page Details\n\n`;
        
        this.results.pages.forEach(page => {
            md += `### ${page.name} (${page.url})\n\n`;
            md += `- **File:** ${page.file}\n`;
            md += `- **Errors:** ${page.errors.length}\n`;
            md += `- **Warnings:** ${page.warnings.length}\n`;
            md += `- **Potential Issues:** ${page.potentialIssues.length}\n\n`;
            
            if (page.errors.length > 0) {
                md += `#### 🚨 Errors\n\n`;
                page.errors.forEach(error => {
                    md += `- **Line ${error.line}** - ${error.message}\n`;
                });
                md += `\n`;
            }
            
            if (page.warnings.length > 0) {
                md += `#### ⚠️ Warnings\n\n`;
                page.warnings.forEach(warning => {
                    md += `- **Line ${warning.line}** - ${warning.message}\n`;
                });
                md += `\n`;
            }
            
            if (page.potentialIssues.length > 0) {
                md += `#### 🔍 Potential Issues\n\n`;
                page.potentialIssues.forEach(issue => {
                    md += `- **Line ${issue.line}** - ${issue.message}\n`;
                });
                md += `\n`;
            }
        });
        
        // Recommendations
        md += `## 🔧 Recommendations\n\n`;
        
        if (summary.totalErrors === 0) {
            md += `✅ **Excellent!** No critical errors found in static analysis.\n\n`;
        } else {
            md += `### Priority Fixes:\n\n`;
            
            if (summary.errorTypes['Mixed Content']) {
                md += `1. **Mixed Content** (${summary.errorTypes['Mixed Content']} instances): Replace HTTP URLs with HTTPS\n`;
            }
            
            if (summary.errorTypes['HTTP URL']) {
                md += `2. **HTTP URLs** (${summary.errorTypes['HTTP URL']} instances): Use HTTPS for all external resources\n`;
            }
            
            if (summary.errorTypes['ReferenceError']) {
                md += `3. **Reference Errors** (${summary.errorTypes['ReferenceError']} instances): Check variable definitions\n`;
            }
            
            md += `\n`;
        }
        
        if (summary.totalWarnings > 3) {
            md += `⚠️ **High Warning Count:** Consider reviewing ${summary.totalWarnings} warnings\n\n`;
        }
        
        md += `---\n`;
        md += `*Report generated by Static Console Scanner*\n`;
        
        return md;
    }
}

// Run the scanner
async function main() {
    try {
        const scanner = new StaticScanner();
        await scanner.scan();
        
        // Exit with appropriate code
        const { summary } = scanner.results;
        if (summary.totalErrors === 0 && summary.totalWarnings <= 3) {
            console.log('🎉 SUCCESS: 0 errors and ≤3 warnings!');
            process.exit(0);
        } else {
            console.log(`❌ ISSUES FOUND: ${summary.totalErrors} errors, ${summary.totalWarnings} warnings`);
            process.exit(1);
        }
        
    } catch (error) {
        console.error('💥 Scanner failed:', error);
        process.exit(1);
    }
}

main();