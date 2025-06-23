<?php

namespace App\Filament\Resources;

use Filament\Forms;
use Filament\Tables;
use App\Models\Seller;
use Filament\Forms\Form;
use Filament\Tables\Table;
use Filament\Resources\Resource;
use App\Models\User; // Import model User
use Illuminate\Database\Eloquent\Builder;
use App\Models\Address; // Import model Address
use App\Models\Regency; // Import model Regency
use App\Models\Village; // Import model Village
use App\Filament\Resources\SellerResource\Pages;
use App\Models\District; // Import model District
use App\Models\Province; // Import model Province
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Filament\Forms\Set; // Untuk mengatur nilai field lain
use App\Filament\Resources\SellerResource\RelationManagers;
use Filament\Forms\Components\Repeater; // Untuk multi-alamat
use Filament\Forms\Get; // Untuk mendapatkan nilai field lain
use Illuminate\Support\Str; // Untuk Str::slug dan Str::random
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Filament\Forms\Components\Section; // Untuk layouting Section

class SellerResource extends Resource
{
    protected static ?string $model = Seller::class;

    protected static ?string $navigationIcon = 'heroicon-o-building-storefront';
    protected static ?string $navigationGroup = 'Manajemen Pengguna';
    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Group::make()
                    ->schema([
                        Section::make('Informasi Toko')
                            ->description('Detail profil dan informasi umum toko.')
                            ->schema([
                                Forms\Components\Select::make('user_id')
                                    ->label('Pilih Pengguna')
                                    ->relationship('user', 'name')
                                    ->options(
                                        fn(?Seller $record): array =>
                                        User::where('user_type', 'seller')
                                            ->when($record, fn($query) => $query->orWhere('id', $record->user_id))
                                            ->whereDoesntHave('seller', fn($query) => $query->when($record, fn($q) => $q->where('id', '!=', $record->id)))
                                            ->pluck('name', 'id')
                                            ->toArray()
                                    )
                                    ->searchable()
                                    ->preload()
                                    ->required()
                                    ->unique(ignoreRecord: true, table: 'sellers', column: 'user_id')
                                    ->helperText('Pilih pengguna yang akan menjadi pemilik toko ini. Hanya user dengan tipe "Penjual" yang belum memiliki toko lain yang akan muncul.'),

                                Forms\Components\TextInput::make('store_name')
                                    ->label('Nama Toko')
                                    ->required()
                                    ->maxLength(255)
                                    ->unique(ignoreRecord: true)
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(fn(string $operation, $state, Forms\Set $set) => $set('store_slug', Str::slug($state)))
                                    ->helperText('Nama toko yang akan ditampilkan di marketplace. Contoh: "Toko Elektronik Jaya".'),

                                Forms\Components\TextInput::make('store_slug')
                                    ->label('Slug Toko')
                                    ->required()
                                    ->maxLength(255)
                                    ->unique(ignoreRecord: true)
                                    ->helperText('URL ramah SEO untuk halaman toko. Otomatis terisi dari Nama Toko, bisa diubah manual.'),

                                Forms\Components\Textarea::make('store_description')
                                    ->label('Deskripsi Toko')
                                    ->columnSpanFull()
                                    ->helperText('Deskripsi singkat tentang toko Anda, bisa berisi informasi produk unggulan atau visi misi toko.'),

                                Forms\Components\FileUpload::make('store_logo_path')
                                    ->label('Logo Toko')
                                    ->image()
                                    ->disk('public')
                                    ->directory('seller-logos')
                                    ->getUploadedFileNameForStorageUsing(
                                        fn(TemporaryUploadedFile $file): string => (string) Str::random(32) . '.' . $file->getClientOriginalExtension(),
                                    )
                                    ->helperText('Unggah logo toko Anda. Ukuran ideal: [rekomendasi ukuran piksel].'),

                                Forms\Components\Select::make('status')
                                    ->label('Status Toko')
                                    ->required()
                                    ->options([
                                        'active' => 'Aktif',
                                        'inactive' => 'Tidak Aktif',
                                        'suspended' => 'Ditangguhkan (Suspended)',
                                    ])
                                    ->native(false)
                                    ->default('inactive') // Default 'inactive' saat membuat toko baru, admin perlu mengaktifkan
                                    ->helperText('Atur status operasional toko ini di marketplace.'),
                            ])->columns(2),
                    ])->columnSpan(['lg' => 2]),

                // Bagian untuk Alamat Toko
                Forms\Components\Group::make()
                    ->schema([
                        Section::make('Alamat Toko')
                            ->description('Daftar alamat fisik toko Anda.')
                            ->schema([
                                Repeater::make('addresses') // Relasi polymorphic ke Address dari model Seller
                                    ->relationship('addresses')
                                    ->label('Daftar Alamat Toko')
                                    ->schema([
                                        Forms\Components\TextInput::make('label')
                                            ->required()
                                            ->maxLength(255)
                                            ->helperText('Label alamat (contoh: "Kantor Pusat", "Gudang", "Toko Cabang").'),
                                        Forms\Components\TextInput::make('recipient_name')
                                            ->required()
                                            ->maxLength(255)
                                            ->helperText('Nama penanggung jawab alamat ini.'),
                                        Forms\Components\TextInput::make('phone_number')
                                            ->required()
                                            ->tel()
                                            ->maxLength(20)
                                            ->helperText('Nomor telepon yang dapat dihubungi untuk alamat ini.'),

                                        // Province (Select2 dari model Province)
                                        Forms\Components\Select::make('province')
                                            ->label('Provinsi')
                                            ->options(Province::pluck('name', 'name'))
                                            ->searchable()
                                            ->live()
                                            ->afterStateUpdated(function (Set $set) {
                                                $set('city', null);
                                                $set('district', null);
                                                $set('village', null);
                                            })
                                            ->nullable()
                                            ->required()
                                            ->helperText('Pilih provinsi lokasi toko.'),

                                        // City (Select2 dari model Regency, bergantung Province)
                                        Forms\Components\Select::make('city')
                                            ->label('Kabupaten/Kota')
                                            ->options(
                                                fn(Get $get): array =>
                                                Regency::whereHas('province', fn($query) => $query->where('name', $get('province')))
                                                    ->pluck('name', 'name')
                                                    ->toArray()
                                            )
                                            ->searchable()
                                            ->live()
                                            ->afterStateUpdated(function (Set $set) {
                                                $set('district', null);
                                                $set('village', null);
                                            })
                                            ->nullable()
                                            ->required()
                                            ->helperText('Pilih kabupaten/kota lokasi toko, setelah memilih provinsi.'),

                                        // District (Select2 dari model District, bergantung City)
                                        Forms\Components\Select::make('district')
                                            ->label('Kecamatan')
                                            ->options(
                                                fn(Get $get): array =>
                                                District::whereHas('regency', fn($query) => $query->where('name', $get('city')))
                                                    ->pluck('name', 'name')
                                                    ->toArray()
                                            )
                                            ->searchable()
                                            ->live()
                                            ->afterStateUpdated(function (Set $set) {
                                                $set('village', null);
                                            })
                                            ->nullable()
                                            ->required()
                                            ->helperText('Pilih kecamatan lokasi toko, setelah memilih kabupaten/kota.'),

                                        // Village (Select2 dari model Village, bergantung District)
                                        Forms\Components\Select::make('village')
                                            ->label('Kelurahan/Desa')
                                            ->options(
                                                fn(Get $get): array =>
                                                Village::whereHas('district', fn($query) => $query->where('name', $get('district')))
                                                    ->pluck('name', 'name')
                                                    ->toArray()
                                            )
                                            ->searchable()
                                            ->nullable()
                                            ->helperText('Pilih kelurahan/desa lokasi toko, setelah memilih kecamatan.'),

                                        Forms\Components\Textarea::make('detail_address')
                                            ->required()
                                            ->columnSpanFull()
                                            ->helperText('Detail alamat lengkap toko (nama jalan, nomor gedung, RT/RW, patokan).'),
                                        Forms\Components\TextInput::make('postal_code')
                                            ->maxLength(10)
                                            ->nullable()
                                            ->helperText('Kode pos alamat toko.'),
                                        Forms\Components\TextInput::make('latitude')
                                            ->numeric()
                                            ->nullable()
                                            ->helperText('Koordinat Latitude alamat toko (opsional).'),
                                        Forms\Components\TextInput::make('longitude')
                                            ->numeric()
                                            ->nullable()
                                            ->helperText('Koordinat Longitude alamat toko (opsional).'),
                                        Forms\Components\Toggle::make('is_default')
                                            ->label('Atur sebagai Alamat Utama Toko')
                                            ->helperText('Centang untuk menjadikan alamat ini sebagai alamat utama toko.'),
                                    ])
                                    ->columns(2)
                                    ->defaultItems(1)
                                    ->minItems(1)
                                    ->itemLabel(fn(array $state): ?string => $state['label'] ?? null)
                                    ->collapsed(),
                            ]),
                    ])->columnSpan(['lg' => 1]),
            ])->columns(3);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('user.name') // Menampilkan nama pengguna terkait
                    ->label('Nama Pengguna')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('store_name')
                    ->label('Nama Toko')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('store_slug')
                    ->label('Slug Toko')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\ImageColumn::make('store_logo_path')
                    ->label('Logo Toko')
                    ->circular()
                    ->size(50),
                Tables\Columns\TextColumn::make('status')
                    ->badge() // Tampilkan sebagai badge
                    ->color(fn(string $state): string => match ($state) {
                        'active' => 'success',
                        'inactive' => 'warning',
                        'suspended' => 'danger',
                    })
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'active' => 'Aktif',
                        'inactive' => 'Tidak Aktif',
                        'suspended' => 'Ditangguhkan',
                    ])
                    ->label('Filter Status Toko'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
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
            'index' => Pages\ListSellers::route('/'),
            'create' => Pages\CreateSeller::route('/create'),
            'edit' => Pages\EditSeller::route('/{record}/edit'),
        ];
    }

    public static function getRedirectUrl(): ?string
    {
        return static::getUrl('index');
    }
}
