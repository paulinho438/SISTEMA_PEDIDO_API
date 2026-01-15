<?php

namespace Database\Seeders;

use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $now = Carbon::now();

        DB::table("users")->insert([
            "nome_completo"     => "ADMIN",
            "login"             => "admin",
            "cpf"               => "05546356154",
            "rg"                => "2834868",
            "sexo"              => "M",
            "telefone_celular"  => "(61) 9 9330-5267",
            "email"             => "admin@gmail.com",
            "status"            => "A",
            "status_motivo"     => "",
            "tentativas"        => 0,
            "password"          => bcrypt("1234")
        ]);
    }
}
