<?php

namespace App\Filament\Resources;

use Filament\Forms;
use Filament\Tables;
use App\Models\Brand;
use Filament\Forms\Form;
use Filament\Tables\Table;
use Filament\Resources\Resource;
use Illuminate\Database\Eloquent\Builder;
use App\Filament\Resources\BrandResource\Pages;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use App\Filament\Resources\BrandResource\RelationManagers;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Filament\Forms\Get; // Tambahkan ini jika menggunakan Get di form
use Filament\Forms\Set; // Tambahkan ini jika menggunakan Set di form
use Illuminate\Support\Str; // Tambahkan ini untuk Str::slug dan Str::random
use Filament\Forms\Components\Section; // Tambahkan ini untuk layouting Section

class BrandResource extends Resource
{
    protected static ?string $model = Brand::class;

    protected static ?string $navigationIcon = 'heroicon-o-tag'; // Ikon lebih sesuai untuk Brand
    protected static ?string $navigationGroup = 'Manajemen Produk'; // Grup navigasi
    protected static ?int $navigationSort = 2; // Urutan setelah Kategori

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Informasi Brand')
                    ->description('Detail dasar dan optimasi SEO untuk brand.')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Nama Brand')
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true) // Nama brand harus unik
                            ->live(onBlur: true) // Aktifkan live mode saat blur untuk slug
                            ->afterStateUpdated(fn(string $operation, $state, Forms\Set $set) => $set('slug', Str::slug($state))) // Slug otomatis
                            ->helperText('Nama brand yang akan ditampilkan. Contoh: "Apple", "Nike", "Samsung".'),

                        Forms\Components\TextInput::make('slug')
                            ->label('Slug Brand')
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true) // Slug harus unik
                            ->helperText('URL ramah SEO untuk halaman brand. Otomatis terisi dari Nama Brand.'),

                        Forms\Components\FileUpload::make('logo_path')
                            ->label('Logo Brand')
                            ->image()
                            ->disk('public')
                            ->directory('brand-logos')
                            ->getUploadedFileNameForStorageUsing(
                                fn(TemporaryUploadedFile $file): string => (string) Str::random(32) . '.' . $file->getClientOriginalExtension(),
                            )
                            ->helperText('Unggah logo brand. Ukuran ideal: [rekomendasi ukuran piksel].'),

                        Forms\Components\Textarea::make('description')
                            ->label('Deskripsi Brand')
                            ->columnSpanFull()
                            ->helperText('Deskripsi singkat tentang brand ini.'),

                        Forms\Components\Toggle::make('status') // Status dibuat Toggle
                            ->label('Status Aktif')
                            ->required()
                            ->onIcon('heroicon-s-check-circle')
                            ->offIcon('heroicon-s-x-circle')
                            ->onColor('success')
                            ->offColor('danger')
                            ->default(true) // Default aktif saat membuat brand baru
                            ->helperText('Atur apakah brand ini aktif dan terlihat di website.'),
                    ])->columns(2),

                Section::make('Pengaturan SEO')
                    ->description('Optimasi untuk mesin pencari.')
                    ->collapsible()
                    ->schema([
                        Forms\Components\TextInput::make('meta_title')
                            ->maxLength(255)
                            ->helperText('Judul meta untuk SEO (maks. 60-70 karakter). Akan muncul di hasil pencarian Google.'),
                        Forms\Components\Textarea::make('meta_description')
                            ->columnSpanFull()
                            ->helperText('Deskripsi meta untuk SEO (maks. 150-160 karakter). Juga muncul di hasil pencarian.'),
                    ])->columns(1), // Mengatur kolom di dalam section SEO
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Nama Brand')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('slug')
                    ->label('Slug Brand')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\ImageColumn::make('logo_path')
                    ->label('Logo')
                    ->circular() // Opsional: Tampilkan gambar sebagai lingkaran
                    ->size(50),
                Tables\Columns\ToggleColumn::make('status') // Status sebagai ToggleColumn
                    ->label('Status Aktif'),
                Tables\Columns\TextColumn::make('meta_title')
                    ->label('Meta Judul')
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
                // Tables\Columns\TextColumn::make('deleted_at') // Hapus jika Brand tidak menggunakan SoftDeletes
                //     ->dateTime()
                //     ->sortable()
                //     ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('status')
                    ->label('Status Brand')
                    ->placeholder('Semua')
                    ->trueLabel('Aktif')
                    ->falseLabel('Tidak Aktif')
                    ->attribute('status')
                    ->queries(
                        true: fn(Builder $query) => $query->where('status', 'active'),
                        false: fn(Builder $query) => $query->where('status', 'inactive'),
                        blank: fn(Builder $query) => $query,
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
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListBrands::route('/'),
            'create' => Pages\CreateBrand::route('/create'),
            'edit' => Pages\EditBrand::route('/{record}/edit'),
        ];
    }
}
