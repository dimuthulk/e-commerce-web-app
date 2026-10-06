<?php
require_once 'config.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard | Dimuthu's E-Commerce</title>
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>

    <!-- Custom Tailwind Configuration -->
    <script src="assets/js/tailwind-config.js"></script>

    <!-- Google Fonts (Inter) -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; }
    </style>
</head>
<body class="bg-slate-50 min-h-screen">

    <!-- Navigation Bar -->
    <nav class="bg-primary text-white shadow-lg">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-center justify-between h-16">
                <div class="flex items-center gap-2 text-xl font-bold tracking-wide">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
                    Dimuthu's App
                </div>
                <div class="text-sm font-medium bg-primary-dark px-3 py-1 text-black rounded-full shadow-inner">
                    Admin Panel
                </div>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10">
        
        <!-- Welcome Section & Status -->
        <div class="flex flex-col md:flex-row md:items-center md:justify-between mb-8 gap-4">
            <div>
                <h1 class="text-3xl font-bold text-slate-800">Welcome back! 👋</h1>
                <p class="text-slate-500 mt-1">Here is the latest data from your MySQL database.</p>
            </div>
            
            <!-- Connection Success Badge -->
            <div class="inline-flex items-center gap-2 bg-emerald-50 border border-emerald-200 text-emerald-700 px-4 py-2 rounded-lg shadow-sm">
                <svg class="w-5 h-5 text-emerald-500" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path></svg>
                <span class="font-medium text-sm">Database Connected</span>
            </div>
        </div>

        <!-- Data Table Section -->
        <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
            <div class="px-6 py-5 border-b border-slate-200 bg-slate-50 flex justify-between items-center">
                <h3 class="text-lg font-semibold text-slate-800">Registered Users</h3>
                <span class="bg-primary-light text-primary-dark text-xs font-bold px-2.5 py-1 rounded-full">Active</span>
            </div>
            
            <div class="overflow-x-auto">
                <table class="w-full whitespace-nowrap">
                    <thead class="bg-slate-50 text-slate-500 text-sm uppercase font-semibold text-left">
                        <tr>
                            <th class="px-6 py-4 tracking-wider">User ID</th>
                            <th class="px-6 py-4 tracking-wider">Full Name</th>
                            <th class="px-6 py-4 tracking-wider">Email Address</th>
                            <th class="px-6 py-4 tracking-wider text-right">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200 text-slate-700">
                        <?php
                        try {
                            $stmt = $pdo->query("SELECT * FROM users ORDER BY id DESC");
                            if($stmt->rowCount() > 0) {
                                while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                                    echo "<tr class='hover:bg-slate-50 transition duration-150 ease-in-out'>";
                                    echo "<td class='px-6 py-4 text-sm text-slate-500 font-medium'>#" . str_pad($row['id'], 4, '0', STR_PAD_LEFT) . "</td>";
                                    echo "<td class='px-6 py-4 text-sm font-semibold text-slate-800'>" . htmlspecialchars($row['name']) . "</td>";
                                    echo "<td class='px-6 py-4 text-sm text-slate-500'>" . htmlspecialchars($row['email']) . "</td>";
                                    echo "<td class='px-6 py-4 text-sm text-right'>
                                            <span class='inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-800'>Verified</span>
                                          </td>";
                                    echo "</tr>";
                                }
                            } else {
                                echo "<tr><td colspan='4' class='px-6 py-8 text-center text-slate-500'>No users found in the database.</td></tr>";
                            }
                        } catch (PDOException $e) {
                            echo "<tr><td colspan='4' class='px-6 py-8 text-center text-red-500'>Error loading data.</td></tr>";
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </div>

    </main>
</body>
</html>