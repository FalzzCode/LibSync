<?php

namespace App\Http\Controllers;

use App\Models\Book;
use App\Models\Category;
use App\Models\Member;
use App\Services\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ImportController extends Controller
{
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
        $header = fgetcsv($handle);
        $header = array_map(fn ($value) => strtolower(trim((string) preg_replace('/^\xEF\xBB\xBF/', '', $value))), $header ?: []);
        $required = $data['type'] === 'books' ? ['title', 'author', 'category', 'stock'] : ['name', 'phone'];
        if (array_diff($required, $header)) {
            fclose($handle);

            return back()->with('error', 'Kolom CSV tidak sesuai. Kolom wajib: '.implode(', ', $required).'.');
        }
        $count = 0;
        $skipped = 0;
        while (($row = fgetcsv($handle)) !== false) {
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
                if (! trim($row['name']) || ! trim($row['phone']) || ($entryYear !== '' && (! ctype_digit($entryYear) || (int) $entryYear < 1900 || (int) $entryYear > now()->year))) {
                    $skipped++;

                    continue;
                }
                $nis = trim($row['nis'] ?? '');
                $email = trim($row['email'] ?? '');
                $memberByNis = $nis !== '' ? Member::where('nis', $nis)->first() : null;
                $memberByEmail = $email !== '' ? Member::where('email', $email)->first() : null;
                if ($memberByNis && $memberByEmail && $memberByNis->id !== $memberByEmail->id) {
                    $skipped++;

                    continue;
                }
                $member = $memberByNis ?? $memberByEmail ?? Member::where('name', trim($row['name']))->where('phone', trim($row['phone']))->first();
                $memberData = [
                    'name' => trim($row['name']),
                    'class' => trim($row['class'] ?? '') ?: null,
                    'phone' => trim($row['phone']),
                    'address' => trim($row['address'] ?? '') ?: null,
                    'major' => trim($row['major'] ?? '') ?: null,
                    'entry_year' => $entryYear !== '' ? (int) $entryYear : null,
                ];
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
        fclose($handle);

        return back()->with('success', "{$count} data berhasil diimpor".($skipped ? "; {$skipped} baris dilewati karena format tidak valid." : '.'));
    }
}
