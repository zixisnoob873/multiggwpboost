@php
    $customer = $customer ?? null;
    $accountStatus = old('account_status', $customer->account_status ?? 'active');
@endphp

<div class="col-md-6">
  <label class="form-label">First name</label>
  <input class="form-control @error('first_name') is-invalid @enderror" name="first_name" value="{{ old('first_name', $customer->first_name ?? '') }}" required>
  @error('first_name')
    <div class="invalid-feedback">{{ $message }}</div>
  @enderror
</div>

<div class="col-md-6">
  <label class="form-label">Last name</label>
  <input class="form-control @error('last_name') is-invalid @enderror" name="last_name" value="{{ old('last_name', $customer->last_name ?? '') }}" required>
  @error('last_name')
    <div class="invalid-feedback">{{ $message }}</div>
  @enderror
</div>

<div class="col-12">
  <label class="form-label" for="customerNickname">Nickname</label>
  <input
    id="customerNickname"
    class="form-control @error('nickname') is-invalid @enderror"
    name="nickname"
    value="{{ old('nickname', $customer->nickname ?? '') }}"
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
  <input type="email" class="form-control @error('email') is-invalid @enderror" name="email" value="{{ old('email', $customer->email ?? '') }}" required>
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

<div class="col-12 d-flex gap-2">
  <button class="btn btn-danger" type="submit">{{ $submitLabel }}</button>
  <a class="btn btn-outline-secondary" href="{{ $cancelUrl }}">Cancel</a>
</div>
