<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {

    public function up(): void
    {
        // 1) cria colunas se não existirem (já cria nullable)
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'telefone')) {
                $table->string('telefone', 20)->nullable()->after('email');
            }
            if (!Schema::hasColumn('users', 'cpf')) {
                $table->string('cpf', 11)->nullable()->after('telefone');
            }
        });

        // 2) força nullable via SQL (caso já existam como NOT NULL)
        // (isso evita o erro "cannot be null")
        DB::statement("ALTER TABLE `users` MODIFY `telefone` VARCHAR(20) NULL");
        DB::statement("ALTER TABLE `users` MODIFY `cpf` VARCHAR(11) NULL");

        // 3) normaliza vazios -> NULL
        DB::table('users')->where('telefone', '')->update(['telefone' => null]);
        DB::table('users')->where('cpf', '')->update(['cpf' => null]);

        // 4) cria unique só se ainda não existir (checando por SHOW INDEX)
        $idx = collect(DB::select("SHOW INDEX FROM `users`"))->pluck('Key_name')->unique();

        if (!$idx->contains('users_telefone_unique')) {
            DB::statement("ALTER TABLE `users` ADD UNIQUE `users_telefone_unique` (`telefone`)");
        }

        if (!$idx->contains('users_cpf_unique')) {
            DB::statement("ALTER TABLE `users` ADD UNIQUE `users_cpf_unique` (`cpf`)");
        }
    }

    public function down(): void
    {
        // remove índices se existirem
        $idx = collect(DB::select("SHOW INDEX FROM `users`"))->pluck('Key_name')->unique();

        if ($idx->contains('users_telefone_unique')) {
            DB::statement("ALTER TABLE `users` DROP INDEX `users_telefone_unique`");
        }
        if ($idx->contains('users_cpf_unique')) {
            DB::statement("ALTER TABLE `users` DROP INDEX `users_cpf_unique`");
        }

        // remove colunas se existirem
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'cpf')) {
                $table->dropColumn('cpf');
            }
            if (Schema::hasColumn('users', 'telefone')) {
                $table->dropColumn('telefone');
            }
        });
    }
};