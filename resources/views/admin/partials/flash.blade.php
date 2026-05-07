@if(session('status'))
    <div class="alert alert-success admin-alert" role="alert">{{ session('status') }}</div>
@endif

@if($errors->any())
    <div class="alert alert-danger admin-alert" role="alert">
        <div class="fw-semibold mb-1">{{ $errors->count() === 1 ? 'There was a problem with this action.' : 'There were problems with this action.' }}</div>
        <ul class="mb-0 ps-3">
            @foreach($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif
