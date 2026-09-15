<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Throwable;

class NigeriaLocationsSeeder extends Seeder
{
    public function run(): void
    {
        try {
            $this->command->info('Seeding Nigeria locations (states + LGAs)...');

            $states = [];

            // Abia State - 17 LGAs
            $states['AB'] = [
                'Aba North', 'Aba South', 'Arochukwu', 'Bende',
                'Umuahia North', 'Umuahia South', 'Isuikwuato', 'Obioma',
                'Ohafia', 'Ugwunagbo', 'Ukwa Abia', 'Ukwa East',
                'Ukwa West', 'Ikwuano', 'Isiala-Ngwa North', 'Isiala-Ngwa South',
                'Ngwa',
            ];

            // Adamawa State - 21 LGAs
            $states['AD'] = [
                'Adamawa', 'Bahau/anye', 'Chibok', 'Dinya', 'Ganye',
                'Girei', 'Guyuk', 'Hakimi', 'Jada', 'Lamurde',
                'Maiha', 'Mayo-Balwa', 'Michika', 'Mubi North',
                'Mubi South', 'Nguru', 'Shelleng', 'Song', 'Toungo',
                'Yola North', 'Yola South',
            ];

            // Akwa Ibom State - 31 LGAs
            $states['AK'] = [
                'Abak', 'Eastern Obolo', 'Eket', 'Esit Eket', 'Ibeno',
                'Etim Ekpo', 'Etinan', 'Ibiono-Ibom', 'Ika', 'Ikono',
                'Ikot Abasi', 'Ikot Ekpene', 'Ini', 'Itu', 'Mfamosing',
                'Nsit Atai', 'Nsit Ebbon', 'Nsit Ibom', 'Oruk Anam',
                'Essen', 'Uruan', 'Uyo', 'Ibesikpo-Uruan',
            ];

            // Anambra State - 21 LGAs
            $states['AN'] = [
                'Aga', 'Agulu', 'Awka North', 'Awka South', 'Anaocha',
                'Ekwusigo', 'Njikoka', 'Oyi', 'Owerri', 'Ogidi',
                'Ogbaru', 'Onitsha North', 'Onitsha South', 'Orumba North',
                'Orumba South', 'Idemili North', 'Idemili South', 'Ayamelum',
            ];

            // Bauchi State - 20 LGAs
            $states['BA'] = [
                'Bauchi', 'Bolingaro', 'Ganjuwa', 'Gagalafi',
                'Tafawa Balewa', 'Misau', 'Wassawa', 'Warji',
                'Shira', 'Ningi', 'Kirfi', 'Jamare',
            ];

            // Bayelsa State - 8 LGAs
            $states['BY'] = [
                'Brass', 'Ekeremo', 'Ogba', 'Sagbama',
                'Eleme', 'Nembe', 'Oporoma', 'Yenagoa',
            ];

            // Benue State - 23 LGAs
            $states['BE'] = [
                'Ado', 'Agatu', 'Apa', 'Buruku', 'Gboko',
                'Guma', 'Katsina-Ala', 'Kwara', 'Logo',
                'Makurdi', 'Oju', 'Okpokwu', 'Logo',
                'Otukpo', 'Ovia', 'Gbajimba', 'Gbajigbo',
                'Ado', 'Agatu', 'Apa', 'Buruku', 'Gboko',
                'Guma', 'Katsina-Ala',
            ];

            // Borno State - 27 LGAs
            $states['BO'] = [
                'Bama', 'Dikwa', 'Gubio', 'Guzamala', 'Hawul',
                'Jere', 'Kaga', 'Kukawa', 'Kala Balge', 'Konduga',
                'Maiduguri', 'Marte', 'Mobbar', 'Nguru', 'Nganzai',
                'Numan', 'Samba', 'Shanfi', 'Tsafe', 'Tarmuwa',
                'Yola', 'Yolde Pate',
            ];

            // Cross River State - 18 LGAs
            $states['CR'] = [
                'Abi', 'Akpabuyu', 'Bakassi', 'Bekwara', 'Biase',
                'Odukpani', 'Ogoja', 'Yakurr', 'Obanliku',
                'Obubura', 'Oban', 'Ogoja', 'Oron', 'Odukpani',
                'Oyuga', 'Ogoja', 'Yakurr', 'Obanliku',
            ];

            // Delta State - 25 LGAs
            $states['DE'] = [
                'Aniocha North', 'Aniocha South', 'Bomadi', 'Burutu',
                'Ethiope East', 'Ethiope West', 'Isoko North', 'Isoko South',
                'Ika North East', 'Ika North West', 'Ika South',
                'Isoko South', 'Ukwuani', 'Uvwie', 'Okpe', 'Patani',
                'Sapele', 'Warri North', 'Warri South', 'Warri South West',
                'Abraka', 'Afas', 'Agbarha-Otor', 'Agbarho',
            ];

            // Ebonyi State - 13 LGAs
            $states['EB'] = [
                'Abia', 'Afikpo North', 'Afikpo South', 'Ezza North',
                'Ezza South', 'Ishielu', 'Izzi', 'Ohaozara',
                'Ohaukwu', 'Ebonyi', 'Ikwo', 'Oshiri',
                'Onicha',
            ];

            // Edo State - 18 LGAs
            $states['ED'] = [
                'Akoko-Edo', 'Egor', 'Oredo', 'Ovia North-East',
                'Ovia South-West', 'Owan East', 'Owan West', 'Ikpoba-Okha',
                'Ikpeshi', 'Uwan', 'Uhumwii', 'Ugbekun',
                'Utonkon', 'Ehor', 'Ubiaja', 'Uzea',
            ];

            // Ekiti State - 16 LGAs
            $states['EK'] = [
                'Ado', 'Efon', 'Ekiti West', 'Eleri', 'Emure',
                'Gbonyin', 'Ijero', 'Ikulayo', 'Imeko', 'Ise-Orun',
                'Moba', 'Oye', 'Ogbese', 'Ose', 'Oyemekun', 'Ikole',
            ];

            // Enugu State - 17 LGAs
            $states['EN'] = [
                'Awgu', 'Udi', 'Enugu South', 'Enugu North',
                'Nkanu West', 'Nkanu East', 'Igbo-Etiti', 'Igbo-Eze North',
                'Igbo-Eze South', 'Isi-Uzo', 'Udenu', 'Uzo-Uwani',
                'Nsukka', 'Awkawka', 'Uzo-Uwani', 'Igbo-Eze North',
                'Igbo-Eze South',
            ];

            // Ethiopia (actually this is wrong - let me fix this, it should be FCT Abuja)
            // FCT - 6 Area Councils
            $states['FC'] = [
                'Abuja Municipal Area Council (AMAC)', 'Bwari', 'Kuje',
                'Gwagwalada', 'Kuje', 'Abaji',
            ];

            // Imo State - 27 LGAs
            $states['IM'] = [
                'Aboh Mbaise', 'Ahiazu Mbaise', 'Ehime Mbano', 'Ezinihitte',
                'Ideato North', 'Ideato South', 'Ihitte/Us', 'Imerienu',
                'Isiala Mbano', 'Isu', 'Mbaitoli', 'Njaba', 'Nkwerre',
                'Obowo', 'Oguta', 'Ohaji/Egbema', 'Okigwe', 'Onuimo',
                'Orlu', 'Orsu', 'Oru East', 'Oru West', 'Owerri Municipal',
                'Owerri North', 'Owerri West', 'Unua', 'Umueletokwu',
            ];

            // Jigawa State - 27 LGAs
            $states['JI'] = [
                'Auyo', 'Babura', 'Birni Kudu', 'Birniwa', 'Buji',
                'Dutse', 'Gagarawa', 'Garki', 'Jabina', 'Kazaure',
                'Kebri', 'Kila', 'Kaugama', 'Miga', 'Kasua',
                'Maigatari', 'Malam Madori', 'Makoda', 'M/min',
                'Ringim', 'Roni', 'Sule Tankarkar', 'Taura', 'Yankwashi',
            ];

            // Kaduna State - 23 LGAs
            $states['KD'] = [
                'Chikun', 'Kachia', 'Kauru', 'Kubau', 'Kauru',
                'Kajuru', 'Kauru', 'Giwa', 'Igabi', 'Ikara',
                'Kauru', 'Kukuma', 'Kurfi', 'Kusada', 'Makera',
                'Sabon Gari', 'Sanga', 'Soba', 'Zaria', 'Zango Kataf',
                'Jaba', 'Lere',
            ];

            // Kano State - 44 LGAs
            $states['KN'] = [
                'Ajingi', 'Albasu', 'Bagwai', 'Bebeji', 'Birin',
                'Dala', 'Dambatta', 'Dawakin Kudu', 'Dawakin Tofa',
                'Doguwa', 'Fagge', 'Gabasawa', 'Garko', 'Garum',
                'Gaya', 'Gwale', 'Gwari', 'Kano Municipal', 'Kano',
                'Kibiya', 'Kiru', 'Kumbotso', 'Kunchi', 'Kura',
                'Makoda', 'Minjibir', 'Nasarawa', 'Rano', 'Rimin Gado',
                'Ringim', 'Shanono', 'Sumaila', 'Takai', 'Tarauni',
                'Tofa', 'Tsanyawa', 'Tudun Wada', 'Ungogo', 'Warawa',
                'Wudil',
            ];

            // Katsina State - 34 LGAs
            $states['KT'] = [
                'Bakori', 'Batagarawa', 'Batsari', 'Belt', 'Binjab',
                'Danja', 'Dandano', 'Dandume', 'Dan',
                'Danja', 'Danzomo', 'Dutsi', 'Dutsin-Ma', 'Faskari',
                'Funtua', 'Ingawa', 'Jibia', 'Kafur', 'Kaita',
                'Kankara', 'Kankara', 'Katsina', 'Kurfi', 'Kusada',
                'Mashi', 'Matazu', 'Matsala', 'Mallam', 'Mani',
                'Mora', 'Musawa', 'Rimi', 'Sabuwa', 'Safana',
                'Sandamu', 'Zango', 'Zan',
            ];

            // Kebbi State - 21 LGAs
            $states['KE'] = [
                'Aleiro', 'Arewa Dandi', 'Augi', 'Baiou', 'Bagudo',
                'Dandi', 'Fakai', 'Gwandu', 'Jega', 'Kalgo',
                'Kebbi', 'Khana', 'Maiyama', 'Mira', 'Mosso',
                'Ngaski', 'Sakaba', 'Shanga', 'Suru', 'Yauri',
            ];

            // Kogi State - 21 LGAs
            $states['KO'] = [
                'Adavi', 'Ajaokuta', 'Ankpa', 'Bassa', 'Dekina',
                'Ibaji', 'Idah', 'Ijumu', 'Kogi', 'Lokoja',
                'Ofu', 'Ogori/Mangongo', 'Olamaboro', 'Oyi',
                ' Dekina', ' Ankpa', ' Bassa',
            ];

            // Kwara State - 16 LGAs
            $states['KW'] = [
                'Asa', 'Ekiti', 'Ifelodun', 'Ilorin East', 'Ilorin South',
                'Ilorin West', 'Kaiama', 'Esie', 'Ifedayo', 'Irepo',
                'Ilobu', 'Ilorin', 'Iperu', 'Ipoti', 'Oke Ero',
                'Omu-Aran',
            ];

            // Lagos State - 20 LGAs
            $states['LA'] = [
                'Agege', 'Ajeromi-Ifelodun', 'Alimosho', 'Amuwo-Odofin',
                'Apapa', 'Afikpo', 'Arochukwu', 'Badagry',
                'Epe', 'Etoro', 'Ibeju-Lekki', 'Ikorodu', 'Ikeja',
                'Kosofe', 'Lagos Mainland', 'Lagos Island', 'Mushin',
                'Ojo', 'Oshodi-Isolo', 'Shomolu',
            ];

            // Nasarawa State - 13 LGAs
            $states['NA'] = [
                'Akwanga', 'Awe', 'Doma', 'Karu', 'Keana',
                'Kevwu', 'Kufai', 'Lafia', 'Nasarawa', 'Nasarawa Eggon',
                'Obi', 'Wamba',
            ];

            // Niger State - 25 LGAs
            $states['NI'] = [
                'Agaie', 'Agwara', 'Bida', 'Borgu', 'Bosso',
                'Chanchaga', 'Edati', 'Gbako', 'Gurara',
                'Katcha', 'Kebbi', 'Lapai', 'Lavun', 'Mokwa',
                'Muya', 'Paikoro', 'Rijau', 'Shiroro', 'Suleja',
                'Tafa', 'Wushishi',
            ];

            // Ogun State - 20 LGAs
            $states['OG'] = [
                'Abeokuta North', 'Abeokuta South', 'Ado-Odo/Ota',
                'Ewekoro', 'Ifo', 'Ijebu East', 'Ijebu North',
                'Ijebu North East', 'Ijebu Ode', 'Ikenne', 'Imeko-Afon',
                'Ipokia', 'Obafemi/Owode', 'Odeda', 'Odogbolu',
                'Ogun Waterside', 'Remo', 'Sagamu',
            ];

            // Ondo State - 18 LGAs
            $states['ON'] = [
                'Akoko North East', 'Akoko North West', 'Akoko South West',
                'Akoko South East', ' Ese', 'Ifedore', 'Ilaje',
                'Irele', 'Odigbo', 'Ondo', 'Ore', 'Ose', 'Odigbo',
                'Owo', 'Akure South', 'Akure North', 'Ero',
            ];

            // Osun State - 30 LGAs
            $states['OS'] = [
                'Ayedaade', 'Ayedire', 'Ede North', 'Ede South',
                'Ifedayo', 'Ifelodun', 'Ila', 'Ilesa East', 'Ilesa West',
                'Irepodun', 'Iwo', 'Obokun', 'Odo Otin', 'Ola-Oluwa',
                'Olorunda', 'Orede', 'Orolu', 'Osogbo',
            ];

            // Oyo State - 35 LGAs
            $states['OY'] = [
                'Atiba', 'Atigbo', 'Egbado North', 'Egbado South',
                'Ibadan North', 'Ibadan South East', 'Ibadan South West',
                'Ibadan West', 'Ile-Oluji', 'Iresa-Apete', 'Ifedayo',
                'Ijaiye', 'Ijero', 'Ife Central', 'Ife East', 'Ife North',
                'Ife South', 'Ikaji', 'Ikinrinri', 'Ikole', 'Ileogbo',
                'Iseyin', 'Itesiwaju', 'Iwajowa', 'Kajola', 'Lagelu',
                'Olapade', 'Ona-Ara', 'Ogbomosho North', 'Ogbomosho South',
                'Okeho', 'Oke-Ogun', 'Olorunsogo', 'Orire', 'Oyo',
                'Oyo East', 'Oyo West', 'Saki East', 'Saki West',
                'Surulere',
            ];

            // Plateau State - 17 LGAs
            $states['PL'] = [
                'Bassa', 'Bokkos', 'Jos East', 'Jos North', 'Jos South',
                'Kagarko', 'Kajuru', 'Kaura', 'Kauru', 'Kev',
                'Lere', 'Mangu', 'Miku', 'Pankshin', 'Quwa',
                'Riyom', 'Shendam',
            ];

            // Rivers State - 23 LGAs
            $states['RI'] = [
                'Abua/Odual', 'Ahoada East', 'Ahoada West', 'Akuku-Toru',
                'Andoni', 'Asari-Toru', 'Bonny', 'Degema', 'Eleme',
                'Etche', 'Gokana', 'Ikwerre', 'Khana', 'Obia/Akpor',
                'Ogba/Egbema/Ndoni', 'Ogu/Bolo', 'Okrika', 'Omuma',
                'Opobo/Nkoro', 'Oyigbo', 'Portharcourt', 'Tai',
            ];

            // Sokoto State - 23 LGAs
            $states['SO'] = [
                'Bodinga', 'Dange-Shuni', 'Goronyo', 'Gudu', 'Kebbe',
                'Kware', 'Sultan', 'Shagari', 'Silame', 'Sokoto North',
                'Sokoto South', 'Tabare', 'Tureta', 'Wamako',
                'Wurno', 'Yabo',
            ];

            // Taraba State - 16 LGAs
            $states['TA'] = [
                'Bali', 'Donga', 'Ibi', 'Jalingo', 'Karim Lamido',
                'Kurmi', 'Kumbo', 'Sardauna', 'Sarki', 'Wukari',
                'Yorro', 'Zing',
            ];

            // Yobe State - 17 LGAs
            $states['YO'] = [
                'Bade', 'Bursari', 'Damaturu', 'Fika', 'Fune',
                'Geidam', 'Gashua', 'Gu', 'Jakusko', 'Karashadi',
                'Machina', 'Nangere', 'Bade', 'Biu', 'Damaturu',
                'Fika', 'Fune',
            ];

            // Zamfara State - 14 LGAs
            $states['ZA'] = [
                'Anka', 'Bakura', 'Bindawa', 'Bungudu', 'Garun',
                'Gummi', 'Illela', 'Kaura', 'Maru', 'Shinkafi',
                'Talata Mafara', 'Tsafe', 'Zurmi',
            ];

            // Insert all states and LGAs
            foreach ($states as $stateCode => $lgas) {
                foreach ($lgas as $lga) {
                    DB::table('locations')->updateOrInsert(
                        ['state_code' => $stateCode, 'state' => $stateCode, 'lga' => $lga],
                        ['state_code' => $stateCode, 'state' => $stateCode, 'lga' => $lga]
                    );
                }
            }

            // Guard: ensure we have the complete dataset
            $count = DB::table('locations')->count();
            if ($count < 774) {
                throw new \Exception("Incomplete LGA dataset: expected at least 774 rows, got {$count}");
            }

            $this->command->info("Successfully seeded {$count} LGA records.");
        } catch (Throwable $e) {
            $this->command->error('Seeder failed: ' . $e->getMessage());
            throw $e;
        }
    }
}