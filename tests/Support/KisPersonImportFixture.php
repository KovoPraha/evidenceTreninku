<?php
declare(strict_types=1);
function kisPersonImportFixture(): array
{
    return [
        'contract'=>'kis-full-person-v1','source_namespace'=>'servis.kovopraha.cz/2026-10-20',
        'people'=>[[
            'key'=>'O0002','first_name'=>'LOCALHOST','last_name'=>'NEPLATIT','birth_date'=>null,
            'email'=>'','phone'=>'','street'=>'','house_no'=>'','orientation_no'=>'','city'=>'','postcode'=>'',
            'country'=>'Česká republika','contact_address'=>'Adresa LOCALHOST','active'=>true,
            'aliases'=>[['source_key'=>'test-old-a','row'=>2,'username'=>'localhost.neplatit'],['source_key'=>'test-old-b','row'=>3,'username'=>'localhost.neplatit.2']],
        ]],
        'contacts'=>[[
            'key'=>'Z0002.1','member_key'=>null,'names'=>['LOCALHOST Rodič'],'emails'=>['parent@example.test'],'phones'=>[],
            'source_slots'=>['Z0002.1','Z0003.1'],
        ]],
        'links'=>[
            ['source_key'=>'test-old-a:ZZ1','person_key'=>'O0002','contact_key'=>'Z0002.1','source_person_key'=>'test-old-a','slot'=>1,'relationship'=>'matka'],
            ['source_key'=>'test-old-b:ZZ1','person_key'=>'O0002','contact_key'=>'Z0002.1','source_person_key'=>'test-old-b','slot'=>1,'relationship'=>'matka'],
        ],
        'source_archive'=>['people'=>[['id_hash'=>'test-old-a'],['id_hash'=>'test-old-b']],
            'guardians'=>[['id_vyskytu'=>'test-old-a:ZZ1'],['id_vyskytu'=>'test-old-b:ZZ1']]],
    ];
}
