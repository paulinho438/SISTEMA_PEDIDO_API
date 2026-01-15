<?php

namespace Database\Seeders;

use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PermGroupsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        DB::table("permgroups")->insert(
            [ "name" => "Super Administrador", "company_id" => 1 ]
        );

        DB::table("permgroups")->insert(
            [ "name" => "Administrador", "company_id" => 1 ]
        );

        DB::table("permgroups")->insert(
            [ "name" => "Gerente", "company_id" => 1 ]
        );

        DB::table("permgroups")->insert(
            [ "name" => "Operador", "company_id" => 1 ]
        );

        DB::table("permgroups")->insert(
            [ "name" => "Consultor", "company_id" => 1 ]
        );

        DB::table("permgroups")->insert(
            [ "name" => "Super Administrador", "company_id" => 2 ]
        );

        DB::table("permgroups")->insert(
            [ "name" => "Administrador", "company_id" => 2 ]
        );

        DB::table("permgroups")->insert(
            [ "name" => "Gerente", "company_id" => 2 ]
        );

        DB::table("permgroups")->insert(
            [ "name" => "Operador", "company_id" => 2 ]
        );


    }
}
