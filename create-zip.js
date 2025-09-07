const fs = require('fs');
const path = require('path');
const archiver = require('archiver');

class AddonZipper {
    constructor() {
        this.pluginDir = __dirname;
        this.zipName = 'membershiping-inventory-addon.zip';
        
        // Files and folders to exclude from the ZIP
        this.excludePatterns = [
            // Version control
            '.git',
            '.gitignore',
            '.gitattributes',
            
            // Node.js
            'node_modules',
            'package.json',
            'package-lock.json',
            'npm-debug.log',
            'yarn.lock',
            'yarn-error.log',
            
            // Development tools
            '.vscode',
            '.idea',
            '*.sublime-project',
            '*.sublime-workspace',
            
            // Build and distribution
            'create-inventory-addon-zip.bat',
            'create-zip.js',
            '*.zip',
            'dist',
            'build',
            
            // Temporary files
            '*.tmp',
            '*.temp',
            '.DS_Store',
            'Thumbs.db',
            
            // Documentation (keep only essential ones)
            'README.md',
            '*.md',
            
            // Test files
            'test-*.php',
            'debug-*.php',
            'admin-menu-test.php',
            'setup-database.php',
            
            // Backup files
            '*.bak',
            '*.backup',
            '*~',
            
            // Log files
            '*.log',
            'logs',
            
            // Cache
            '.cache',
            'cache'
        ];
    }

    shouldExclude(itemPath) {
        const normalizedPath = itemPath.replace(/\\/g, '/');
        
        return this.excludePatterns.some(pattern => {
            // Handle wildcard patterns
            if (pattern.includes('*')) {
                const regexPattern = pattern
                    .replace(/\./g, '\\.')
                    .replace(/\*/g, '.*');
                const regex = new RegExp(`^${regexPattern}$`, 'i');
                return regex.test(path.basename(normalizedPath));
            }
            
            // Handle exact matches (files and folders)
            return normalizedPath === pattern || 
                   normalizedPath.startsWith(pattern + '/') ||
                   path.basename(normalizedPath) === pattern;
        });
    }

    async createZip() {
        console.log('🔧 MEMBERSHIPING INVENTORY ADDON ZIPPER');
        console.log('========================================');
        console.log('');
        
        // Remove existing ZIP file
        if (fs.existsSync(this.zipName)) {
            console.log(`🗑️  Removing existing ${this.zipName}...`);
            fs.unlinkSync(this.zipName);
        }

        return new Promise((resolve, reject) => {
            // Create a file to stream archive data to
            const output = fs.createWriteStream(this.zipName);
            const archive = archiver('zip', {
                zlib: { level: 9 } // Maximum compression
            });

            // Listen for all archive data to be written
            output.on('close', () => {
                const sizeInMB = (archive.pointer() / (1024 * 1024)).toFixed(2);
                console.log('');
                console.log('✅ ZIP creation completed successfully!');
                console.log(`📦 Archive size: ${archive.pointer()} bytes (${sizeInMB} MB)`);
                console.log(`📄 File: ${this.zipName}`);
                console.log('');
                resolve();
            });

            // Handle warnings
            archive.on('warning', (err) => {
                if (err.code === 'ENOENT') {
                    console.warn('⚠️  Warning:', err.message);
                } else {
                    reject(err);
                }
            });

            // Handle errors
            archive.on('error', (err) => {
                reject(err);
            });

            // Pipe archive data to the file
            archive.pipe(output);

            console.log('📂 Scanning addon directory...');
            console.log(`📁 Source: ${this.pluginDir}`);
            console.log('');

            // Get all items in the plugin directory
            const items = fs.readdirSync(this.pluginDir);
            
            let addedCount = 0;
            
            items.forEach(item => {
                const itemPath = path.join(this.pluginDir, item);
                const stat = fs.statSync(itemPath);
                
                if (!this.shouldExclude(item)) {
                    if (stat.isDirectory()) {
                        console.log(`📁 Adding folder: ${item}/`);
                        archive.directory(itemPath, item);
                    } else {
                        console.log(`📄 Adding file: ${item}`);
                        archive.file(itemPath, { name: item });
                    }
                    addedCount++;
                } else {
                    console.log(`❌ Excluding: ${item}`);
                }
            });
            
            console.log('');
            console.log(`📦 Added ${addedCount} items to ZIP`);
            console.log('⏳ Finalizing ZIP file...');

            // Finalize the archive
            archive.finalize();
        });
    }
}

// Check if archiver is available
try {
    require.resolve('archiver');
} catch (e) {
    console.log('');
    console.log('❌ ERROR: archiver package not found');
    console.log('');
    console.log('Please install it by running:');
    console.log('npm install archiver');
    console.log('');
    console.log('Or use the fallback batch file method.');
    process.exit(1);
}

// Run the zipper
const zipper = new AddonZipper();
zipper.createZip().catch(error => {
    console.error('');
    console.error('❌ ERROR creating ZIP file:');
    console.error(error.message);
    console.error('');
    process.exit(1);
});
