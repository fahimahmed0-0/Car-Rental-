<?php

declare(strict_types=1);

use App\Core\Security;
?>
<header class="sticky top-0 z-40 h-16 bg-slate-950 text-white border-b border-slate-800 px-4 sm:px-6 flex items-center justify-between">
  <div class="flex items-center gap-3">
    <button type="button" @click="sidebarOpen = !sidebarOpen" class="lg:hidden rounded-lg border border-slate-700 p-2 text-slate-200 hover:bg-slate-800" aria-label="Toggle Sidebar">
      <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
    </button>
    <a href="dashboard.php" class="flex items-center gap-2">
      <span class="text-lg font-extrabold tracking-tight">Drive<span class="text-red-500">X</span> <span class="text-sky-400">Admin</span></span>
    </a>
  </div>

  <div class="flex items-center gap-4 text-xs">
    <a href="../index.php" target="_blank" class="hidden sm:inline-flex items-center rounded-lg border border-slate-700 px-3 py-1.5 font-semibold text-slate-200 hover:border-sky-400 hover:text-white transition">
      View Live Site &nearr;
    </a>
    <span class="text-slate-400 hidden md:inline">Signed in as <strong class="text-white"><?php echo Security::e($_SESSION['alogin'] ?? 'admin'); ?></strong></span>
    <a href="change-password.php" class="font-semibold text-sky-300 hover:underline">Password</a>
    <a href="logout.php" class="rounded-lg bg-red-600 px-3 py-1.5 font-semibold text-white hover:bg-red-700 transition">Logout</a>
  </div>
</header>
