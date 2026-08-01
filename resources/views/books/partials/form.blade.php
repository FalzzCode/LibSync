<div class="form-card__header">
    <div>
        <h2>Informasi buku</h2>
        <p>Kolom bertanda * wajib diisi.</p>
    </div>
</div>

<div class="form-grid">
    <div class="form-group form-group--wide">
        <label for="title">Judul buku <span>*</span></label>
        <input id="title" name="title" value="{{ old('title', $book?->title) }}" required @class(['is-invalid' => $errors->has('title')])>
        @error('title') <small class="field-error">{{ $message }}</small> @enderror
    </div>
    <div class="form-group">
        <label for="author">Penulis <span>*</span></label>
        <input id="author" name="author" value="{{ old('author', $book?->author) }}" required @class(['is-invalid' => $errors->has('author')])>
        @error('author') <small class="field-error">{{ $message }}</small> @enderror
    </div>
    <div class="form-group">
        <label for="book_code">Kode koleksi</label>
        <input id="book_code" name="book_code" value="{{ old('book_code', $book?->book_code) }}" maxlength="100">
    </div>
    <div class="form-group">
        <label for="isbn">ISBN</label>
        <input id="isbn" name="isbn" value="{{ old('isbn', $book?->isbn) }}" maxlength="30">
    </div>
    <div class="form-group">
        <label for="publisher">Penerbit</label>
        <input id="publisher" name="publisher" value="{{ old('publisher', $book?->publisher) }}">
    </div>
    <div class="form-group">
        <label for="publication_year">Tahun terbit</label>
        <input id="publication_year" type="number" name="publication_year" min="1000" max="{{ now()->year }}" value="{{ old('publication_year', $book?->publication_year) }}">
    </div>
    <div class="form-group">
        <label for="category_id">Kategori <span>*</span></label>
        <select id="category_id" name="category_id" required @class(['is-invalid' => $errors->has('category_id')])>
            <option value="">Pilih kategori</option>
            @foreach ($categories as $category)
                <option value="{{ $category->id }}" @selected(old('category_id', $book?->category_id) == $category->id)>{{ $category->name }}</option>
            @endforeach
        </select>
        @error('category_id') <small class="field-error">{{ $message }}</small> @enderror
    </div>
    <div class="form-group">
        <label for="stock">Jumlah stok <span>*</span></label>
        <input id="stock" type="number" name="stock" min="0" value="{{ old('stock', $book?->stock ?? 0) }}" required @class(['is-invalid' => $errors->has('stock')])>
        @error('stock') <small class="field-error">{{ $message }}</small> @enderror
    </div>
    <div class="form-group">
        <label for="shelf">Lokasi rak</label>
        <input id="shelf" name="shelf" value="{{ old('shelf', $book?->shelf) }}" maxlength="100">
    </div>
    <div class="form-group">
        <label for="language">Bahasa</label>
        <input id="language" name="language" value="{{ old('language', $book?->language) }}" maxlength="50">
    </div>
    <div class="form-group">
        <label for="page_count">Jumlah halaman</label>
        <input id="page_count" type="number" name="page_count" min="1" max="99999" value="{{ old('page_count', $book?->page_count) }}">
        @error('page_count') <small class="field-error">{{ $message }}</small> @enderror
    </div>
    <div class="form-group form-group--wide">
        <label for="description">Deskripsi singkat</label>
        <textarea id="description" name="description" rows="4" maxlength="5000">{{ old('description', $book?->description) }}</textarea>
    </div>
    <div class="form-group form-group--wide">
        <label for="cover_image">Cover buku {{ $book ? '(opsional)' : '*' }}</label>
        <div class="file-field">
            @if ($book?->cover_image)
                <img class="file-field__preview" src="{{ asset('storage/' . $book->cover_image) }}" alt="Cover saat ini">
            @else
                <div class="file-field__preview" hidden></div>
            @endif
            <label for="cover_image" class="file-field__label"><span aria-hidden="true">↑</span><strong>Unggah cover</strong><small>JPG, PNG, atau WEBP</small></label>
            <input id="cover_image" type="file" name="cover_image" accept="image/*" {{ $book ? '' : 'required' }}>
        </div>
        @error('cover_image') <small class="field-error">{{ $message }}</small> @enderror
    </div>
</div>

<div class="form-actions">
    <a class="btn btn--secondary" href="{{ route('books.index') }}">Batal</a>
    <button class="btn btn--primary" type="submit">{{ $submitLabel }}</button>
</div>
