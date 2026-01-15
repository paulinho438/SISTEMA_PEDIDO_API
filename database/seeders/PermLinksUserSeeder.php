<?php

namespace Database\Seeders;

use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PermLinksUserSeeder extends Seeder
{

    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {




        DB::table("permgroup_user")->insert(
            [
                "permgroup_id"     => 1,
                "user_id"      => 1

            ]
        );

        DB::table("permgroup_user")->insert(
            [
                "permgroup_id"     => 6,
                "user_id"      => 1

            ]
        );


    }
}
