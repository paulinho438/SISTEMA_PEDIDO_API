<?php

namespace Database\Seeders;

use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CompanyUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        DB::table("company_user")->insert(
            [
                "company_id"                => 1,
                "user_id"                   => 1,
            ]
        );

        DB::table("company_user")->insert(
            [
                "company_id"                => 2,
                "user_id"                   => 1,
            ]
        );

    }
}
