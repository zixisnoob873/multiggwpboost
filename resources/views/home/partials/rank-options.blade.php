@php($selected = $selected ?? null)

@foreach ($options as $option)
  <option value="{{ $option }}" @selected($selected === $option)>{{ $option }}</option>
@endforeach
