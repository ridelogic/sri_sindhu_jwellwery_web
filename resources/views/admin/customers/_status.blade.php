{{-- Account status badge (solid): Active / Inactive / Blocked. Expects: $status (AccountStatus) --}}
<span class="admin-status admin-status-{{ $status->value }}" data-account-status="{{ $status->value }}">{{ $status->label() }}</span>
