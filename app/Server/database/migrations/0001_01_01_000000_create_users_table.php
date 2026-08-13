<?php

use App\Enums\UserRole;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // D23. `roles` se crea aqui, y no en su propia migracion, porque
        // `users.role_id` la necesita ya existente y esta es la primera
        // migracion del proyecto.
        Schema::create('roles', function (Blueprint $table) {
            $table->unsignedTinyInteger('id')->primary();
            $table->string('name', 20)->unique();
        });

        // Las dos filas van en la migracion, no en un seeder: `role_id` es
        // NOT NULL y apunta aqui, asi que una base de datos migrada sin
        // sembrar no admitiria ni un solo usuario. Es una tabla de referencia
        // fija, no se gestiona desde el backoffice.
        //
        // La semilla sale del enum para que no puedan divergir.
        DB::table('roles')->insert(
            array_map(
                fn (UserRole $role): array => ['id' => $role->value, 'name' => $role->slug()],
                UserRole::cases(),
            ),
        );

        Schema::create('users', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->rememberToken();

            // NOT NULL y **sin DEFAULT** (D23): un insert incompleto tiene que
            // fallar en vez de conceder un rol por omision. En v1 esto era un
            // varchar cuyo valor por defecto lo ponia el controller, y de ahi
            // salio SEC-001.
            $table->unsignedTinyInteger('role_id');
            $table->foreign('role_id')->references('id')->on('roles')->restrictOnDelete();

            $table->timestamps();
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('password_reset_tokens');
        // Antes que `roles`: la clave foranea apunta hacia alla.
        Schema::dropIfExists('users');
        Schema::dropIfExists('roles');
    }
};
