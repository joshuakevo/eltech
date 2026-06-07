@extends('layouts.app')
@section('title','Database Backup')
@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="mb-0 fw-semibold">Database Backup</h4>
        <p class="text-muted small mb-0">Create and manage database backups</p>
    </div>
    <form method="POST" action="{{ route('backup.create') }}"
          onsubmit="this.querySelector('button').disabled=true; this.querySelector('button').innerHTML='<span class=\'spinner-border spinner-border-sm me-2\'></span>Creating...'">
        @csrf
        <button type="submit" class="btn btn-primary">
            <i class="bi bi-database-add me-1"></i>Create Backup Now
        </button>
    </form>
</div>

@if(session('success'))
    <div class="alert alert-success alert-dismissible fade show">
        <i class="bi bi-check-circle me-2"></i>{{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif
@if($errors->has('error'))
    <div class="alert alert-danger alert-dismissible fade show">
        <i class="bi bi-exclamation-circle me-2"></i>{{ $errors->first('error') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif

<div class="row g-4 mb-4">
    <div class="col-sm-6 col-lg-4">
        <div class="card shadow-sm">
            <div class="card-body">
                <div class="d-flex align-items-center gap-3">
                    <div class="p-3 rounded bg-primary-subtle">
                        <i class="bi bi-database text-primary fs-4"></i>
                    </div>
                    <div>
                        <div class="fs-4 fw-bold">{{ $files->count() }}</div>
                        <div class="text-muted small">Total Backups</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-lg-4">
        <div class="card shadow-sm">
            <div class="card-body">
                <div class="d-flex align-items-center gap-3">
                    <div class="p-3 rounded bg-success-subtle">
                        <i class="bi bi-folder text-success fs-4"></i>
                    </div>
                    <div>
                        <div class="fw-semibold small text-truncate" style="max-width:180px" title="{{ $path }}">
                            {{ $path }}
                        </div>
                        <div class="text-muted small">Backup Path</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    @if($files->isNotEmpty())
    <div class="col-sm-6 col-lg-4">
        <div class="card shadow-sm">
            <div class="card-body">
                <div class="d-flex align-items-center gap-3">
                    <div class="p-3 rounded bg-info-subtle">
                        <i class="bi bi-clock text-info fs-4"></i>
                    </div>
                    <div>
                        <div class="fw-semibold">{{ $files->first()['date'] }}</div>
                        <div class="text-muted small">Latest Backup</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    @endif
</div>

<div class="card shadow-sm">
    <div class="card-header fw-semibold">Backup Files</div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Filename</th>
                    <th>Date</th>
                    <th>Size</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
            @forelse($files as $file)
                <tr>
                    <td>
                        <i class="bi bi-file-earmark-zip text-muted me-2"></i>
                        {{ $file['name'] }}
                    </td>
                    <td class="text-muted">{{ $file['date'] }}</td>
                    <td>{{ $file['size'] }}</td>
                    <td class="text-end">
                        <div class="d-flex gap-1 justify-content-end">
                            <a href="{{ route('backup.download', $file['name']) }}"
                               class="btn btn-sm btn-outline-primary">
                                <i class="bi bi-download me-1"></i>Download
                            </a>
                            <form method="POST" action="{{ route('backup.destroy', $file['name']) }}"
                                  onsubmit="return confirm('Delete this backup file?')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-outline-danger">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </form>
                        </div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="4" class="text-center py-5 text-muted">
                        <i class="bi bi-database-x d-block fs-1 opacity-25 mb-2"></i>
                        No backups found. Create one now.
                    </td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="alert alert-info mt-4 small">
    <i class="bi bi-info-circle me-1"></i>
    <strong>Note:</strong> Backups are saved to <code>{{ $path }}</code> using <code>mysqldump</code>.
    You can change the backup path in <a href="{{ route('settings.index') }}">System Settings</a>.
</div>
@endsection
