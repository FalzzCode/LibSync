<?php

namespace App\Http\Controllers;

use App\Models\Book;
use App\Models\Category;
use App\Models\Member;
use App\Services\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use RuntimeException;

class ImportController extends Controller
{
    private const MAX_ROWS = 10000;

    private const CSV_HEADER_ALIASES = [
        'judul' => 'title',
        'title' => 'title',
        'penulis' => 'author',
        'author' => 'author',
        'kategori' => 'category',
        'category' => 'category',
        'stok' => 'stock',
        'stock' => 'stock',
        'kode_buku' => 'book_code',
        'book_code' => 'book_code',
        'penerbit' => 'publisher',
        'publisher' => 'publisher',
        'tahun_terbit' => 'publication_year',
        'publication_year' => 'publication_year',
        'nama' => 'name',
        'nama_lengkap' => 'name',
        'name' => 'name',
        'nomor_telepon' => 'phone',
        'nomor_hp' => 'phone',
        'no_telepon' => 'phone',
        'no_hp' => 'phone',
        'telepon' => 'phone',
        'phone' => 'phone',
        'email_google' => 'email',
        'email' => 'email',
        'kelas' => 'class',
        'class' => 'class',
        'jurusan' => 'major',
        'major' => 'major',
        'alamat' => 'address',
        'address' => 'address',
        'tahun_masuk' => 'entry_year',
        'entry_year' => 'entry_year',
    ];

    public function create(): View
    {
        return view('imports.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'type' => ['required', 'in:books,members'],
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:2048'],
        ]);
        $handle = fopen($data['file']->getRealPath(), 'r');
        if (! is_resource($handle)) {
            return redirect()->route('imports.create')->with('error', 'File CSV tidak dapat dibaca. Simpan ulang file sebagai CSV UTF-8 lalu coba lagi.');
        }
        $header = fgetcsv($handle);
        $header = array_map(fn ($value) => $this->normalizeCsvHeader($value), $header ?: []);
        if (count($header) !== count(array_unique($header))) {
            fclose($handle);

            return redirect()->route('imports.create')->with('error', 'Nama kolom CSV tidak boleh berulang. Periksa baris pertama file.');
        }
        $required = $data['type'] === 'books' ? ['title', 'author', 'category', 'stock'] : ['name'];
        if (array_diff($required, $header)) {
            fclose($handle);
            $requiredLabels = $data['type'] === 'books'
                ? ['judul', 'penulis', 'kategori', 'stok']
                : ['nama'];

            return redirect()->route('imports.create')->with('error', 'Nama kolom CSV tidak sesuai. Kolom wajib: '.implode(', ', $requiredLabels).'.');
        }
        $count = 0;
        $skipped = 0;

        try {
            DB::transaction(function () use ($handle, $data, $header, &$count, &$skipped): void {
                $rowNumber = 0;
                while (($row = fgetcsv($handle)) !== false) {
                    if (++$rowNumber > self::MAX_ROWS) {
                        throw new RuntimeException('csv_row_limit');
                    }
                    if (count($row) !== count($header)) {
                        $skipped++;

                        continue;
                    }
                    $row = array_combine($header, $row);
                    if ($data['type'] === 'books') {
                        $publicationYear = trim($row['publication_year'] ?? '');
                        if (! trim($row['title']) || ! trim($row['author']) || ! trim($row['category']) || ! is_numeric($row['stock']) || (int) $row['stock'] < 0 || ($publicationYear !== '' && (! ctype_digit($publicationYear) || (int) $publicationYear < 1000 || (int) $publicationYear > now()->year))) {
                            $skipped++;

                            continue;
                        }
                        $category = Category::firstOrCreate(['name' => trim($row['category'])]);
                        $bookCode = trim($row['book_code'] ?? '');
                        $book = $bookCode !== ''
                            ? Book::where('book_code', $bookCode)->first()
                            : Book::where('title', trim($row['title']))->where('author', trim($row['author']))->first();
                        $bookData = [
                            'title' => trim($row['title']),
                            'author' => trim($row['author']),
                            'publisher' => trim($row['publisher'] ?? '') ?: null,
                            'publication_year' => $publicationYear !== '' ? (int) $publicationYear : null,
                            'stock' => (int) $row['stock'],
                            'category_id' => $category->id,
                        ];
                        if ($bookCode !== '' || ! $book) {
                            $bookData['book_code'] = $bookCode ?: null;
                        }
                        if ($book) {
                            $book->update($bookData);
                        } else {
                            $book = Book::create($bookData);
                        }
                        ActivityLogger::write('import', 'book', $book, null, ['source' => 'csv']);
                    } else {
                        $entryYear = trim($row['entry_year'] ?? '');
                        $phone = array_key_exists('phone', $row) ? trim((string) $row['phone']) : null;
                        $nis = trim($row['nis'] ?? '');
                        $email = strtolower(trim($row['email'] ?? ''));
                        if (! trim($row['name']) || ($phone !== null && $phone !== '' && ! preg_match('/^[0-9+()\-\s]+$/', $phone)) || ($email !== '' && ! filter_var($email, FILTER_VALIDATE_EMAIL)) || ($entryYear !== '' && (! ctype_digit($entryYear) || (int) $entryYear < 1900 || (int) $entryYear > now()->year))) {
                            $skipped++;

                            continue;
                        }
                        $memberByNis = $nis !== '' ? Member::withTrashed()->where('nis', $nis)->first() : null;
                        $memberByEmail = $email !== '' ? Member::withTrashed()->where('email', $email)->first() : null;
                        if ($memberByNis?->trashed() || $memberByEmail?->trashed()) {
                            $skipped++;

                            continue;
                        }
                        if ($memberByNis && $memberByEmail && $memberByNis->id !== $memberByEmail->id) {
                            $skipped++;

                            continue;
                        }
                        $member = $memberByNis ?? $memberByEmail;
                        // Without a stable identity, a name alone is not
                        // enough to decide that two rows represent one
                        // person. Create a new row instead of silently
                        // merging students who share the same name.
                        if (! $member && $phone !== null && $phone !== '') {
                            $member = Member::where('name', trim($row['name']))
                                ->where('phone', $phone)
                                ->first();
                        }
                        $memberData = [
                            'name' => trim($row['name']),
                            'class' => trim($row['class'] ?? '') ?: null,
                            'address' => trim($row['address'] ?? '') ?: null,
                            'major' => trim($row['major'] ?? '') ?: null,
                            'entry_year' => $entryYear !== '' ? (int) $entryYear : null,
                        ];
                        if ($phone !== null || ! $member) {
                            $memberData['phone'] = $phone ?: null;
                        }
                        if ($nis !== '' || ! $member) {
                            $memberData['nis'] = $nis ?: null;
                        }
                        if ($email !== '' || ! $member) {
                            $memberData['email'] = $email ?: null;
                        }
                        if ($member) {
                            $member->update($memberData);
                        } else {
                            $member = Member::create($memberData);
                        }
                        ActivityLogger::write('import', 'member', $member, null, ['source' => 'csv']);
                    }
                    $count++;
                }
            });
        } catch (RuntimeException $exception) {
            if ($exception->getMessage() === 'csv_row_limit') {
                return redirect()->route('imports.create')->with('error', 'File CSV terlalu besar. Maksimal 10.000 baris per impor.');
            }

            throw $exception;
        } finally {
            if (is_resource($handle)) {
                fclose($handle);
            }
        }

        return redirect()->route('imports.create')->with('success', "{$count} data berhasil diimpor".($skipped ? "; {$skipped} baris dilewati karena format tidak valid." : '.'));
    }

    private function normalizeCsvHeader(mixed $value): string
    {
        $header = strtolower(trim((string) preg_replace('/^\xEF\xBB\xBF/', '', $value)));
        $header = preg_replace('/[\s-]+/', '_', $header) ?: '';
        $header = preg_replace('/[^a-z0-9_]/', '', $header) ?: '';

        return self::CSV_HEADER_ALIASES[$header] ?? $header;
    }
}
