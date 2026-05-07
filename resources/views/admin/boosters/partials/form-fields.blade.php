@php
    $booster = $booster ?? null;
    $sourceApplication = $sourceApplication ?? null;
    $sourceNameParts = preg_split('/\s+/', trim((string) ($sourceApplication->name ?? '')), 2) ?: [];
    $accountStatus = old('account_status', $booster->account_status ?? 'active');
    $firstName = old('first_name', $booster->first_name ?? ($sourceNameParts[0] ?? ''));
    $lastName = old('last_name', $booster->last_name ?? ($sourceNameParts[1] ?? ''));
    $nickname = old('nickname', $booster->nickname ?? $sourceApplication?->nickname ?? '');
    $email = old('email', $booster->email ?? $sourceApplication?->email ?? '');
@endphp

<div class="col-md-6">
  <label class="form-label">First name</label>
  <input class="form-control @error('first_name') is-invalid @enderror" name="first_name" value="{{ $firstName }}" required>
  @error('first_name')
    <div class="invalid-feedback">{{ $message }}</div>
  @enderror
</div>

<div class="col-md-6">
  <label class="form-label">Last name</label>
  <input class="form-control @error('last_name') is-invalid @enderror" name="last_name" value="{{ $lastName }}" required>
  @error('last_name')
    <div class="invalid-feedback">{{ $message }}</div>
  @enderror
</div>

<div class="col-12">
  <label class="form-label" for="boosterNickname">Nickname</label>
  <input
    id="boosterNickname"
    class="form-control @error('nickname') is-invalid @enderror"
    name="nickname"
    value="{{ $nickname }}"
    maxlength="25"
    pattern="[A-Za-z0-9]+"
    inputmode="text"
    autocomplete="nickname"
    data-nickname-input
    required
  >
  <div class="form-text">Letters and numbers only, up to 25 characters.</div>
  <div class="invalid-feedback" data-nickname-feedback>Use only letters and numbers, with no spaces or symbols.</div>
  @error('nickname')
    <div class="invalid-feedback d-block">{{ $message }}</div>
  @enderror
</div>

<div class="col-12">
  <label class="form-label">Email</label>
  <input type="email" class="form-control @error('email') is-invalid @enderror" name="email" value="{{ $email }}" required>
  @error('email')
    <div class="invalid-feedback">{{ $message }}</div>
  @enderror
</div>

<div class="col-12">
  <label class="form-label">{{ $passwordLabel }}</label>
  <input type="password" class="form-control @error('password') is-invalid @enderror" name="password" {{ $passwordRequired ? 'required' : '' }}>
  @if (! empty($passwordHelp))
    <div class="form-text">{{ $passwordHelp }}</div>
  @endif
  @error('password')
    <div class="invalid-feedback">{{ $message }}</div>
  @enderror
</div>

<div class="col-12">
  <label class="form-label">Account status</label>
  <select class="form-select @error('account_status') is-invalid @enderror" name="account_status" required>
    <option value="active" {{ $accountStatus === 'active' ? 'selected' : '' }}>Active</option>
    <option value="suspended" {{ $accountStatus === 'suspended' ? 'selected' : '' }}>Suspended</option>
  </select>
  @error('account_status')
    <div class="invalid-feedback">{{ $message }}</div>
  @enderror
</div>

<div class="col-12 d-flex flex-wrap gap-2 booster-form-actions">
  <button class="btn btn-danger px-4" type="submit">{{ $submitLabel }}</button>
  <a class="btn btn-outline-secondary px-4" href="{{ $cancelUrl }}">Cancel</a>
</div>
