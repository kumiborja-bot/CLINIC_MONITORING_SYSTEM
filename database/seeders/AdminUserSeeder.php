<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'fretzborja1@gmail.com'],
            [
                'name' => 'OSAS',
                'password' => bcrypt('OSAS2026!'),
                'role' => 'admin',
            ]
        );

        User::updateOrCreate(
            ['email' => 'mmaciclinic54@gmail.com'],
            [
                'name' => 'Ms. Sherill Ampoloquio',
                'password' => bcrypt('HEADNURSE2026!'),
                'role' => 'head_nurse',
            ]
        );

        User::updateOrCreate(
            ['email' => 'joey.oliva@mmaci.edu.ph'],
            [
                'name' => 'DR. JOEVY JAY C. OLIVA, MD, FPSMS',
                'role' => 'doctor',
            ]
        );

        User::updateOrCreate(
            ['email' => 'gemmalyn.hermoso@mmaci.edu.ph'],
            [
                'name' => 'DR. GEMMALYN G. HERMOSO',
                'role' => 'doctor',
            ]
        );

        User::updateOrCreate(
            ['email' => 'kumiborja@gmail.com'],
            [
                'name' => 'Mr. Cyril Beltran',
                'password' => bcrypt('ASSISTANTNURSE2026!'),
                'role' => 'assistant_nurse',
            ]
        );
    }
}
