<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'kumiborja@gmail.com'],
            [
                'name' => 'Admin',
                'password' => bcrypt('password'),
                'role' => 'admin',
            ]
        );

        User::updateOrCreate(
            ['email' => 'mmaciclinic54@gmail.com'],
            [
                'name' => 'Nurse',
                'password' => bcrypt('password'),
                'role' => 'nurse',
            ]
        );

        User::updateOrCreate(
            ['email' => 'joey.oliva@mmaci.edu.ph'],
            [
                'name' => 'DR. JOEVY JAY C. OLIVA, MD, FPSMS',
                'password' => bcrypt('password'),
                'role' => 'doctor',
            ]
        );

        User::updateOrCreate(
            ['email' => 'gemmalyn.hermoso@mmaci.edu.ph'],
            [
                'name' => 'DR. GEMMALYN G. HERMOSO',
                'password' => bcrypt('password'),
                'role' => 'doctor',
            ]
        );
    }
}
