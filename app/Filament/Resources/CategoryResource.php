<?php

namespace App\Filament\Resources;

use Filament\Forms;
use Filament\Tables;
use App\Models\Category;
use Filament\Forms\Form;
use Filament\Tables\Table;
use Filament\Resources\Resource;
use Illuminate\Support\Facades\Storage;
use Illuminate\Database\Eloquent\Builder;
use App\Filament\Resources\CategoryResource\Pages;
use Illuminate\Support\Str; // Tambahkan ini untuk Str::slug
use App\Filament\Resources\CategoryResource\RelationManagers;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class CategoryResource extends Resource
{
    protected static ?string $model = Category::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    // Sesuaikan Label dan Grup Navigasi
    protected static ?string $navigationGroup = 'Manajemen Produk'; // Opsional: Tambahkan grup navigasi
    protected static ?int $navigationSort = 1; // Opsional: Atur urutan navigasi

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->required()
                    ->maxLength(255)
                    ->live(onBlur: true) // Aktifkan live mode saat blur untuk slug
                    ->afterStateUpdated(fn(string $operation, $state, Forms\Set $set) => $operation === 'create' ? $set('slug', Str::slug($state)) : null)
                    ->helperText('Nama kategori yang akan ditampilkan di website. Contoh: "Elektronik", "Fashion Pria".'),

                Forms\Components\TextInput::make('slug')
                    ->required()
                    ->maxLength(255)
                    ->unique(ignoreRecord: true) // Pastikan slug unik, abaikan saat edit
                    ->helperText('URL ramah SEO untuk kategori ini. Otomatis terisi dari Nama, bisa diubah manual.'),

                Forms\Components\Textarea::make('description')
                    ->columnSpanFull()
                    ->helperText('Deskripsi singkat tentang kategori ini.'),

                Forms\Components\FileUpload::make('image_path')
                    ->image()
                    ->disk('public') // Pastikan menggunakan disk 'public' atau yang sesuai
                    ->directory('categories') // Simpan gambar di folder 'categories' dalam disk 'public'
                    ->getUploadedFileNameForStorageUsing(
                        fn(TemporaryUploadedFile $file): string => (string) Str::random(32) . '.' . $file->getClientOriginalExtension(),
                    )
                    ->columnSpanFull()
                    ->helperText('Gambar representasi untuk kategori ini. Ukuran ideal: [rekomendasi ukuran piksel].'),

                Forms\Components\Select::make('parent_id')
                    ->label('Kategori Induk')
                    ->relationship('parent', 'name') // 'parent' adalah nama relasi di Category model
                    ->placeholder('Pilih Kategori Induk (opsional)')
                    ->searchable() // Mengaktifkan fitur pencarian
                    ->preload() // Memuat semua opsi di awal (hati-hati jika data sangat banyak)
                    ->nullable() // Memungkinkan kategori tanpa induk
                    ->options(function () { // Menggunakan closure untuk opsi
                        $options = static::getHierarchicalCategoryOptions(); // Ambil opsi hierarkis

                        $currentRecordId = null;
                        if ($record = request()->route('record')) {
                            if (is_object($record) && method_exists($record, 'getKey')) {
                                $currentRecordId = $record->getKey();
                            } else {
                                $currentRecordId = $record;
                            }
                        }

                        if ($currentRecordId) {
                            unset($options[$currentRecordId]);
                        }
                        return collect($options)->prepend('No Parent', null)->all();
                    })
                    ->helperText('Pilih kategori lain jika ini adalah sub-kategori.'),

                Forms\Components\Toggle::make('status')
                    ->required()
                    ->onIcon('heroicon-s-check-circle')
                    ->offIcon('heroicon-s-x-circle')
                    ->onColor('success')
                    ->offColor('danger')
                    ->default(true) // Default aktif saat membuat kategori baru
                    ->helperText('Atur apakah kategori ini aktif dan terlihat di website.'),

                Forms\Components\Section::make('Pengaturan SEO')
                    ->description('Optimasi untuk mesin pencari.')
                    ->collapsible()
                    ->schema([
                        Forms\Components\TextInput::make('meta_title')
                            ->maxLength(255)
                            ->helperText('Judul meta untuk SEO (maks. 60-70 karakter). Akan muncul di hasil pencarian Google.'),
                        Forms\Components\Textarea::make('meta_description')
                            ->helperText('Deskripsi meta untuk SEO (maks. 150-160 karakter). Juga muncul di hasil pencarian.'),
                    ])->columns(1), // Mengatur kolom di dalam section SEO
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('slug')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\ImageColumn::make('image_path')
                    ->label('Gambar')
                    ->circular() // Membuat gambar berbentuk lingkaran
                    ->url(fn($record) => Storage::url($record->image))
                    ->size(50),
                Tables\Columns\TextColumn::make('parent.name') // Menampilkan nama kategori induk
                    ->label('Kategori Induk')
                    ->placeholder('Tidak Ada')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: false),
                Tables\Columns\ToggleColumn::make('status') // Toggle langsung di tabel
                    ->label('Status Aktif'),
                Tables\Columns\TextColumn::make('meta_title')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true), // Sembunyikan secara default
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('deleted_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('parent_id')
                    ->label('Kategori Induk')
                    ->relationship('parent', 'name')
                    ->placeholder('Semua Kategori Induk'),
                Tables\Filters\TernaryFilter::make('status')
                    ->label('Status Kategori')
                    ->placeholder('Semua')
                    ->trueLabel('Aktif')
                    ->falseLabel('Tidak Aktif')
                    ->attribute('status')
                    ->queries(
                        true: fn(Builder $query) => $query->where('status', 'active'),
                        false: fn(Builder $query) => $query->whereIn('status', ['inactive']), // Perhatikan jika ada status 'banned' atau 'suspended'
                        blank: fn(Builder $query) => $query, // Tampilkan semua jika kosong
                    ),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(), // Tambahkan aksi delete
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            // Tambahkan relasi manager di sini jika ada (contoh: ProductRelationManager jika ingin mengelola produk dari halaman kategori)
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCategories::route('/'),
            'create' => Pages\CreateCategory::route('/create'),
            'edit' => Pages\EditCategory::route('/{record}/edit'),
        ];
    }

    /**
     * Helper method to generate hierarchical category options for Select fields.
     */
    protected static function getHierarchicalCategoryOptions(): array
    {
        // Eager load all necessary parent levels to prevent N+1 queries during path building.
        // Adjust the depth (e.g., .parent.parent.parent.parent) based on your maximum expected hierarchy.
        $categories = Category::with('parent')->get();
        $options = [];

        foreach ($categories as $category) {
            $path = [$category->name]; // Mulai dengan nama kategori itu sendiri
            $current = $category;

            // Traverse ke atas ke parent root
            // Gunakan `relationLoaded('parent')` untuk memastikan relasi sudah di-eager load
            // dan tidak memicu kueri N+1
            while ($current->relationLoaded('parent') && $current->parent) {
                array_unshift($path, $current->parent->name); // Tambahkan nama parent ke awal
                $current = $current->parent;
            }

            $options[$category->id] = implode(' > ', $path);
        }

        // Urutkan opsi secara alfabetis berdasarkan jalur tampilan mereka
        asort($options);

        return $options;
    }
}
