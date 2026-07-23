<?php

namespace Database\Seeders;

use App\Models\Country;
use Illuminate\Database\Seeder;

class CountriesSeeder extends Seeder
{
    public function run(): void
    {
        $countries = [
            ['name' => 'Argentina', 'code' => 'AR'],
            ['name' => 'Bolivia', 'code' => 'BO'],
            ['name' => 'Brasil', 'code' => 'BR'],
            ['name' => 'Chile', 'code' => 'CL'],
            ['name' => 'Colombia', 'code' => 'CO'],
            ['name' => 'Ecuador', 'code' => 'EC'],
            ['name' => 'Paraguay', 'code' => 'PY'],
            ['name' => 'Perú', 'code' => 'PE'],
            ['name' => 'Uruguay', 'code' => 'UY'],
            ['name' => 'Venezuela', 'code' => 'VE'],

            ['name' => 'Alemania', 'code' => 'DE'],
            ['name' => 'Austria', 'code' => 'AT'],
            ['name' => 'Bélgica', 'code' => 'BE'],
            ['name' => 'Croacia', 'code' => 'HR'],
            ['name' => 'Dinamarca', 'code' => 'DK'],
            ['name' => 'Escocia', 'code' => 'SCT'],
            ['name' => 'España', 'code' => 'ES'],
            ['name' => 'Francia', 'code' => 'FR'],
            ['name' => 'Gales', 'code' => 'WLS'],
            ['name' => 'Grecia', 'code' => 'GR'],
            ['name' => 'Hungría', 'code' => 'HU'],
            ['name' => 'Inglaterra', 'code' => 'ENG'],
            ['name' => 'Irlanda', 'code' => 'IE'],
            ['name' => 'Irlanda del Norte', 'code' => 'NIR'],
            ['name' => 'Islandia', 'code' => 'IS'],
            ['name' => 'Italia', 'code' => 'IT'],
            ['name' => 'Noruega', 'code' => 'NO'],
            ['name' => 'Países Bajos', 'code' => 'NL'],
            ['name' => 'Polonia', 'code' => 'PL'],
            ['name' => 'Portugal', 'code' => 'PT'],
            ['name' => 'República Checa', 'code' => 'CZ'],
            ['name' => 'Rumania', 'code' => 'RO'],
            ['name' => 'Serbia', 'code' => 'RS'],
            ['name' => 'Suecia', 'code' => 'SE'],
            ['name' => 'Suiza', 'code' => 'CH'],
            ['name' => 'Turquía', 'code' => 'TR'],
            ['name' => 'Ucrania', 'code' => 'UA'],

            ['name' => 'Canadá', 'code' => 'CA'],
            ['name' => 'Costa Rica', 'code' => 'CR'],
            ['name' => 'El Salvador', 'code' => 'SV'],
            ['name' => 'Estados Unidos', 'code' => 'US'],
            ['name' => 'Guatemala', 'code' => 'GT'],
            ['name' => 'Honduras', 'code' => 'HN'],
            ['name' => 'Jamaica', 'code' => 'JM'],
            ['name' => 'México', 'code' => 'MX'],
            ['name' => 'Panamá', 'code' => 'PA'],
            ['name' => 'Trinidad y Tobago', 'code' => 'TT'],

            ['name' => 'Argelia', 'code' => 'DZ'],
            ['name' => 'Camerún', 'code' => 'CM'],
            ['name' => 'Costa de Marfil', 'code' => 'CI'],
            ['name' => 'Egipto', 'code' => 'EG'],
            ['name' => 'Ghana', 'code' => 'GH'],
            ['name' => 'Marruecos', 'code' => 'MA'],
            ['name' => 'Nigeria', 'code' => 'NG'],
            ['name' => 'Senegal', 'code' => 'SN'],
            ['name' => 'Sudáfrica', 'code' => 'ZA'],
            ['name' => 'Túnez', 'code' => 'TN'],

            ['name' => 'Arabia Saudita', 'code' => 'SA'],
            ['name' => 'Australia', 'code' => 'AU'],
            ['name' => 'China', 'code' => 'CN'],
            ['name' => 'Corea del Sur', 'code' => 'KR'],
            ['name' => 'Emiratos Árabes Unidos', 'code' => 'AE'],
            ['name' => 'Irán', 'code' => 'IR'],
            ['name' => 'Japón', 'code' => 'JP'],
            ['name' => 'Qatar', 'code' => 'QA'],

            ['name' => 'Mundial', 'code' => 'WW'],
            ['name' => 'CONMEBOL', 'code' => 'CS'],
            ['name' => 'UEFA', 'code' => 'EU'],
            ['name' => 'FIFA', 'code' => 'FI'],
            ['name' => 'Sudamérica', 'code' => 'SA'],
        ];

        foreach ($countries as $country) {
            Country::updateOrCreate(
                ['code' => $country['code']],
                [
                    'name' => $country['name'],
                    'flag_url' => $country['code'] === 'WW' || strlen($country['code']) > 2
                        ? null
                        : "https://flagcdn.com/w80/".strtolower($country['code']).".png",
                ],
            );
        }
    }
}
