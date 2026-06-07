<?php

namespace App\Http\Controllers;

use App\Models\SystemSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class BackupController extends Controller
{
    private function backupPath(): string
    {
        $path = SystemSetting::get('backup_path', storage_path('backups'));
        if (!File::exists($path)) {
            File::makeDirectory($path, 0755, true);
        }
        return rtrim($path, '/\\');
    }

    public function index()
    {
        $path    = $this->backupPath();
        $files   = collect(File::files($path))
            ->filter(fn($f) => str_ends_with($f->getFilename(), '.sql'))
            ->sortByDesc(fn($f) => $f->getMTime())
            ->values()
            ->map(fn($f) => [
                'name'     => $f->getFilename(),
                'size'     => $this->formatBytes($f->getSize()),
                'date'     => date('Y-m-d H:i:s', $f->getMTime()),
            ]);

        return view('backup.index', compact('files', 'path'));
    }

    public function create(Request $request)
    {
        $dbHost   = config('database.connections.mysql.host', '127.0.0.1');
        $dbPort   = config('database.connections.mysql.port', '3306');
        $dbName   = config('database.connections.mysql.database');
        $dbUser   = config('database.connections.mysql.username');
        $dbPass   = config('database.connections.mysql.password');

        $filename = 'backup_' . date('Ymd_His') . '.sql';
        $outPath  = $this->backupPath() . DIRECTORY_SEPARATOR . $filename;

        // Try to find mysqldump
        $mysqldump = $this->findMysqldump();

        if (!$mysqldump) {
            return back()->withErrors(['error' => 'mysqldump not found. Please ensure MySQL is installed and configure the backup path in Settings.']);
        }

        $passArg = $dbPass ? "-p" . escapeshellarg($dbPass) : '';
        $cmd = sprintf(
            '"%s" -h %s -P %s -u %s %s %s > "%s" 2>&1',
            $mysqldump,
            escapeshellarg($dbHost),
            escapeshellarg($dbPort),
            escapeshellarg($dbUser),
            $passArg,
            escapeshellarg($dbName),
            $outPath
        );

        exec($cmd, $output, $returnCode);

        if ($returnCode !== 0 || !File::exists($outPath) || File::size($outPath) === 0) {
            if (File::exists($outPath)) File::delete($outPath);
            return back()->withErrors(['error' => 'Backup failed. Output: ' . implode(' ', $output)]);
        }

        return back()->with('success', "Backup created: {$filename}");
    }

    public function download(string $filename): BinaryFileResponse
    {
        $path = $this->backupPath() . DIRECTORY_SEPARATOR . basename($filename);

        abort_unless(File::exists($path), 404);

        return response()->download($path);
    }

    public function destroy(string $filename)
    {
        $path = $this->backupPath() . DIRECTORY_SEPARATOR . basename($filename);

        if (File::exists($path)) {
            File::delete($path);
        }

        return back()->with('success', 'Backup deleted.');
    }

    private function findMysqldump(): ?string
    {
        $candidates = [
            'C:/xampp/mysql/bin/mysqldump.exe',
            'C:/Program Files/MySQL/MySQL Server 8.0/bin/mysqldump.exe',
            'C:/Program Files/MySQL/MySQL Server 8.4/bin/mysqldump.exe',
            '/usr/bin/mysqldump',
            '/usr/local/bin/mysqldump',
        ];

        foreach ($candidates as $path) {
            if (file_exists($path)) return $path;
        }

        // Try PATH
        exec('where mysqldump 2>NUL', $out, $rc);
        if ($rc === 0 && !empty($out[0])) return trim($out[0]);

        exec('which mysqldump 2>/dev/null', $out, $rc);
        if ($rc === 0 && !empty($out[0])) return trim($out[0]);

        return null;
    }

    private function formatBytes(int $bytes): string
    {
        if ($bytes >= 1048576) return round($bytes / 1048576, 2) . ' MB';
        if ($bytes >= 1024) return round($bytes / 1024, 1) . ' KB';
        return $bytes . ' B';
    }
}
