<?php

// app/Filament/Resources/UserResource.php

namespace App\Filament\Resources;

use App\Filament\Resources\UserResource\Pages;
use App\Filament\Resources\UserResource\RelationManagers;
use App\Models\User;
use App\Models\Address;
use App\Models\Province; // Import model Province
use App\Models\Regency; // Import model Regency (Kabupaten/Kota)
use App\Models\District; // Import model District (Kecamatan)
use App\Models\Village; // Import model Village (Kelurahan/Desa)
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Hash;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Section;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Illuminate\Support\Str;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile; // Tambahkan ini jika diperlukan untuk FileUpload

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $navigationIcon = 'heroicon-o-users';
    protected static ?string $navigationGroup = 'Manajemen Pengguna';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Group::make()
                    ->schema([
                        Section::make('Informasi Akun Pengguna')
                            ->description('Detail login dan peran pengguna.')
                            ->schema([
                                Forms\Components\TextInput::make('name')
                                    ->required()
                                    ->maxLength(255)
                                    ->helperText('Nama lengkap pengguna.'),

                                Forms\Components\TextInput::make('email')
                                    ->email()
                                    ->required()
                                    ->maxLength(255)
                                    ->unique(ignoreRecord: true)
                                    ->helperText('Alamat email pengguna. Harus unik.'),

                                Forms\Components\DateTimePicker::make('email_verified_at')
                                    ->label('Email Diverifikasi Pada')
                                    ->nullable()
                                    ->helperText('Tanggal dan waktu email pengguna diverifikasi.'),

                                Forms\Components\TextInput::make('password')
                                    ->password()
                                    ->maxLength(255)
                                    ->dehydrateStateUsing(fn(string $state): string => Hash::make($state))
                                    ->dehydrated(fn(?string $state): bool => filled($state))
                                    ->required(fn(string $operation): bool => $operation === 'create')
                                    ->helperText('Kata sandi pengguna. Kosongkan jika tidak ingin mengubah.')
                                    ->revealable(),

                                Forms\Components\Select::make('user_type')
                                    ->required()
                                    ->options([
                                        'admin' => 'Admin Aplikasi',
                                        'seller' => 'Penjual',
                                        'customer' => 'Pembeli',
                                    ])
                                    ->native(false)
                                    ->helperText('Pilih peran pengguna dalam aplikasi.'),

                                Forms\Components\Toggle::make('status')
                                    ->required()
                                    ->onIcon('heroicon-s-check-circle')
                                    ->offIcon('heroicon-s-x-circle')
                                    ->onColor('success')
                                    ->offColor('danger')
                                    ->default(true)
                                    ->helperText('Atur apakah akun pengguna ini aktif atau diblokir.'),
                            ])->columns(2),

                        Section::make('Detail Profil Pribadi')
                            ->description('Informasi pribadi pengguna.')
                            ->schema([
                                Forms\Components\FileUpload::make('profile_picture') // Nama kolom disesuaikan menjadi 'profile_picture'
                                    ->label('Foto Profil')
                                    ->image()
                                    ->disk('public')
                                    ->directory('profile-pictures')
                                    ->getUploadedFileNameForStorageUsing(
                                        fn(TemporaryUploadedFile $file): string => (string) Str::random(32) . '.' . $file->getClientOriginalExtension(),
                                    )
                                    ->avatar()
                                    ->helperText('Unggah foto profil pengguna. Ukuran ideal: 200x200 piksel.'),

                                Forms\Components\Select::make('gender')
                                    ->options([
                                        'male' => 'Laki-laki',
                                        'female' => 'Perempuan',
                                    ])
                                    ->native(false)
                                    ->nullable()
                                    ->helperText('Pilih jenis kelamin pengguna.'),

                                Forms\Components\DatePicker::make('date_of_birth')
                                    ->label('Tanggal Lahir')
                                    ->maxDate(now())
                                    ->nullable()
                                    ->helperText('Tanggal lahir pengguna.'),

                                Forms\Components\TextInput::make('phone_number')
                                    ->label('Nomor Handphone')
                                    ->tel()
                                    ->maxLength(20)
                                    ->nullable()
                                    ->unique(ignoreRecord: true, table: 'users')
                                    ->helperText('Nomor telepon seluler pengguna.'),
                            ])->columns(2),
                    ])->columnSpan(['lg' => 2]),

                Forms\Components\Group::make()
                    ->schema([
                        Section::make('Alamat Pribadi')
                            ->description('Daftar alamat pribadi pengguna.')
                            ->schema([
                                Repeater::make('addresses')
                                    ->relationship('addresses')
                                    ->label('Daftar Alamat')
                                    ->schema([
                                        Forms\Components\TextInput::make('label')
                                            ->required()
                                            ->maxLength(255)
                                            ->helperText('Label alamat (contoh: "Rumah", "Kantor").'),
                                        Forms\Components\TextInput::make('recipient_name')
                                            ->required()
                                            ->maxLength(255)
                                            ->helperText('Nama penerima paket atau penanggung jawab alamat.'),
                                        Forms\Components\TextInput::make('phone_number')
                                            ->required()
                                            ->tel()
                                            ->maxLength(20)
                                            ->helperText('Nomor telepon yang dapat dihubungi untuk alamat ini.'),

                                        // 1. Province (Select2 dari model Province)
                                        Forms\Components\Select::make('province')
                                            ->label('Provinsi')
                                            ->options(Province::pluck('name', 'name')) // Ambil nama provinsi dari model Province
                                            ->searchable()
                                            ->live() // Aktifkan live mode untuk memuat City
                                            ->afterStateUpdated(function (Set $set) {
                                                // Reset City, District, Village saat Province berubah
                                                $set('city', null);
                                                $set('district', null);
                                                $set('village', null);
                                            })
                                            ->nullable()
                                            ->required() // Provinsi wajib diisi
                                            ->helperText('Pilih provinsi alamat.'),

                                        // 2. City (Select2 dari model Regency, bergantung Province)
                                        Forms\Components\Select::make('city')
                                            ->label('Kabupaten/Kota')
                                            ->options(
                                                fn(Get $get): array =>
                                                Regency::whereHas('province', fn($query) => $query->where('name', $get('province')))
                                                    ->pluck('name', 'name')
                                                    ->toArray()
                                            )
                                            ->searchable()
                                            ->live() // Aktifkan live mode untuk memuat District
                                            ->afterStateUpdated(function (Set $set) {
                                                // Reset District, Village saat City berubah
                                                $set('district', null);
                                                $set('village', null);
                                            })
                                            ->nullable()
                                            ->required() // Kota wajib diisi
                                            ->helperText('Pilih kabupaten/kota alamat, setelah memilih provinsi.'),

                                        // 3. District (Select2 dari model District, bergantung City)
                                        Forms\Components\Select::make('district')
                                            ->label('Kecamatan')
                                            ->options(
                                                fn(Get $get): array =>
                                                District::whereHas('regency', fn($query) => $query->where('name', $get('city')))
                                                    ->pluck('name', 'name')
                                                    ->toArray()
                                            )
                                            ->searchable()
                                            ->live() // Aktifkan live mode untuk memuat Village
                                            ->afterStateUpdated(function (Set $set) {
                                                // Reset Village saat District berubah
                                                $set('village', null);
                                            })
                                            ->nullable()
                                            ->required() // Kecamatan wajib diisi
                                            ->helperText('Pilih kecamatan alamat, setelah memilih kabupaten/kota.'),

                                        // 4. Village (Select2 dari model Village, bergantung District)
                                        Forms\Components\Select::make('village')
                                            ->label('Kelurahan/Desa')
                                            ->options(
                                                fn(Get $get): array =>
                                                Village::whereHas('district', fn($query) => $query->where('name', $get('district')))
                                                    ->pluck('name', 'name')
                                                    ->toArray()
                                            )
                                            ->searchable()
                                            ->nullable() // Kelurahan/desa bisa opsional
                                            ->helperText('Pilih kelurahan/desa alamat, setelah memilih kecamatan.'),

                                        Forms\Components\Textarea::make('detail_address')
                                            ->required()
                                            ->columnSpanFull()
                                            ->helperText('Detail alamat lengkap (nama jalan, nomor rumah, RT/RW, patokan).'),
                                        Forms\Components\TextInput::make('postal_code')
                                            ->maxLength(10)
                                            ->nullable()
                                            ->helperText('Kode pos alamat.'),
                                        Forms\Components\TextInput::make('latitude')
                                            ->numeric()
                                            ->nullable()
                                            ->helperText('Koordinat Latitude alamat (opsional).'),
                                        Forms\Components\TextInput::make('longitude')
                                            ->numeric()
                                            ->nullable()
                                            ->helperText('Koordinat Longitude alamat (opsional).'),
                                        Forms\Components\Toggle::make('is_default')
                                            ->label('Atur sebagai Alamat Utama')
                                            ->helperText('Centang untuk menjadikan alamat ini sebagai alamat utama pengguna.'),
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
                Tables\Columns\TextColumn::make('uuid')
                    ->searchable()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\ImageColumn::make('profile_picture') // Nama kolom disesuaikan
                    ->label('Foto Profil')
                    ->circular()
                    ->size(40),
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('email')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('user_type')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->searchable()
                    ->sortable(),
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
                Tables\Filters\SelectFilter::make('user_type')
                    ->options([
                        'admin' => 'Admin',
                        'seller' => 'Penjual',
                        'customer' => 'Pembeli',
                    ])
                    ->label('Filter Tipe Pengguna'),
                Tables\Filters\TernaryFilter::make('status')
                    ->label('Status Akun')
                    ->placeholder('Semua')
                    ->trueLabel('Aktif')
                    ->falseLabel('Tidak Aktif/Dibanned')
                    ->attribute('status')
                    ->queries(
                        true: fn(Builder $query) => $query->where('status', 'active'),
                        false: fn(Builder $query) => $query->whereIn('status', ['inactive', 'banned']),
                        blank: fn(Builder $query) => $query,
                    ),
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
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }
}
