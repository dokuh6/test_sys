<?php
require_once 'includes/init.php';
require_once 'includes/header.php';
?>

<div class="max-w-5xl mx-auto bg-surface-light dark:bg-surface-dark rounded-xl shadow-xl overflow-hidden transition-colors duration-200">
    <div class="py-12 px-6 text-center">
        <h2 class="text-2xl md:text-3xl font-bold mb-6 text-gray-800 dark:text-white">ようこそ、ゲストハウス丸正へ</h2>
        <div class="space-y-4 text-gray-600 dark:text-gray-300 leading-relaxed">
            <p class="text-lg">心安らぐひとときをお過ごしください。</p>
            <p>当ゲストハウスは、静かな環境と温かいおもてなしで、お客様の旅の疲れを癒します。</p>
        </div>
    </div>
    <div class="bg-gray-50 dark:bg-gray-800/50 mx-6 mb-12 rounded-lg p-8 border border-gray-100 dark:border-gray-700">
        <h3 class="text-xl font-bold text-center mb-8 text-gray-800 dark:text-white flex items-center justify-center gap-2">
            <span class="material-icons text-primary dark:text-blue-400">search</span>
            <?php echo h(t('index_search_title')); ?>
        </h3>
        <form class="flex flex-col md:flex-row gap-6 justify-center items-end" action="search_results.php" method="GET">
            <div class="w-full md:w-auto flex-1">
                <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2" for="check_in_date"><?php echo h(t('form_check_in')); ?>:</label>
                <div class="relative">
                    <input class="w-full rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring focus:ring-primary focus:ring-opacity-50 dark:bg-gray-700 dark:border-gray-600 dark:text-white py-2.5 px-3" id="check_in_date" name="check_in_date" type="date" required />
                </div>
            </div>
            <div class="w-full md:w-auto flex-1">
                <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2" for="check_out_date"><?php echo h(t('form_check_out')); ?>:</label>
                <div class="relative">
                    <input class="w-full rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring focus:ring-primary focus:ring-opacity-50 dark:bg-gray-700 dark:border-gray-600 dark:text-white py-2.5 px-3" id="check_out_date" name="check_out_date" type="date" required />
                </div>
            </div>
            <div class="w-full md:w-40">
                <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2" for="num_guests"><?php echo h(t('form_num_guests')); ?>:</label>
                <select class="w-full rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring focus:ring-primary focus:ring-opacity-50 dark:bg-gray-700 dark:border-gray-600 dark:text-white py-2.5 px-3" id="num_guests" name="num_guests">
                    <option value="1">1名</option>
                    <option value="2">2名</option>
                    <option value="3">3名</option>
                    <option value="4">4名</option>
                    <option value="5">5名以上</option>
                </select>
            </div>
            <div class="w-full md:w-auto">
                <button class="w-full bg-primary hover:bg-primary-dark text-white font-bold py-2.5 px-8 rounded-md shadow transition-colors duration-200 flex items-center justify-center gap-2" type="submit">
                    <span class="material-icons text-sm">search</span>
                    <?php echo h(t('btn_search')); ?>
                </button>
            </div>
        </form>
    </div>
    <div class="px-8 pb-16">
        <div class="border-t border-gray-200 dark:border-gray-700 pt-10">
            <h3 class="text-xl font-bold mb-8 text-gray-800 dark:text-white flex items-center gap-2">
                <span class="material-icons text-primary dark:text-blue-400">hotel</span>
                お部屋のご紹介
            </h3>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow-md hover:shadow-xl transition-shadow duration-300 overflow-hidden border border-gray-100 dark:border-gray-700 flex flex-col group">
                    <div class="relative overflow-hidden h-48">
                        <img alt="Single Room" class="w-full h-full object-cover transform group-hover:scale-105 transition-transform duration-500" src="https://lh3.googleusercontent.com/aida-public/AB6AXuD4wOyNOj9ML7n2yLokhV_2z2PV6TC2xZbu38efiYtu5jwVY8QyRarf0NNx9gf3CYv_vzytWdnKZ5mDE0hAfJGbWK8PkLAsDwYXzK5Mu90ehP8PuxxJKCS5gFmKmImVec_nmucJmCh4qYTD4C9e-G7c95pEjfwuP78G4QMI8dB1NEi4ha0iAUhINlAxmeuRA0ZhVPtgUWtY0outu2gz--QDIRp1_nvNUyGYWg6RBc0fzga2xbAPF2uCV2-4rQEry2YYko8yrY1AafzZ"/>
                    </div>
                    <div class="p-6 flex flex-col flex-grow">
                        <h4 class="text-lg font-bold text-gray-800 dark:text-white mb-2">スタンダードシングル</h4>
                        <p class="text-sm text-gray-600 dark:text-gray-300 mb-6 flex-grow leading-relaxed">
                            シンプルで機能的なお部屋です。ビジネスや一人旅に最適です。
                        </p>
                        <a href="rooms.php" class="w-full py-2.5 px-4 rounded border border-primary text-primary hover:bg-primary hover:text-white dark:border-blue-400 dark:text-blue-400 dark:hover:bg-blue-400 dark:hover:text-gray-900 font-semibold transition-all duration-200 flex items-center justify-center gap-1 group-hover:gap-2">
                            詳細を見る
                            <span class="material-icons text-sm">arrow_forward</span>
                        </a>
                    </div>
                </div>
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow-md hover:shadow-xl transition-shadow duration-300 overflow-hidden border border-gray-100 dark:border-gray-700 flex flex-col group">
                    <div class="relative overflow-hidden h-48">
                        <img alt="Twin Room" class="w-full h-full object-cover transform group-hover:scale-105 transition-transform duration-500" src="https://lh3.googleusercontent.com/aida-public/AB6AXuAj_JOAx5GOmb0GfLcB6Sz4ure5HOfhptCSP9kEhtJVfqo_N5MkZovuO0ptoz-Tb4_5Mt-M-wtJeNPcuD1ZA_mu-APqiMF8UTQ9-5N_8KBrzKGxvFLoX3CUNMxi0oIzZYUCAil4DPUn1kcz3uwNjw0kNhFSTCQZUaDiyKLE6ynHev1j7r2j89Qn7TKtHA97NJ14lPizRobxIcIpYjA8sFdpbRlFM9lCYwGPoThGaW5Atw9dUPIeU6V58poTTme7RkWN3bVpZOYYrUJh"/>
                    </div>
                    <div class="p-6 flex flex-col flex-grow">
                        <h4 class="text-lg font-bold text-gray-800 dark:text-white mb-2">デラックスツイン</h4>
                        <p class="text-sm text-gray-600 dark:text-gray-300 mb-6 flex-grow leading-relaxed">
                            ゆったりとくつろげる広めのお部屋です。カップルやご友人とのご旅行におすすめです。
                        </p>
                        <a href="rooms.php" class="w-full py-2.5 px-4 rounded border border-primary text-primary hover:bg-primary hover:text-white dark:border-blue-400 dark:text-blue-400 dark:hover:bg-blue-400 dark:hover:text-gray-900 font-semibold transition-all duration-200 flex items-center justify-center gap-1 group-hover:gap-2">
                            詳細を見る
                            <span class="material-icons text-sm">arrow_forward</span>
                        </a>
                    </div>
                </div>
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow-md hover:shadow-xl transition-shadow duration-300 overflow-hidden border border-gray-100 dark:border-gray-700 flex flex-col group">
                    <div class="relative overflow-hidden h-48">
                        <img alt="Japanese Style Room" class="w-full h-full object-cover transform group-hover:scale-105 transition-transform duration-500" src="https://lh3.googleusercontent.com/aida-public/AB6AXuALAc0hXw_1oar6Z8XsKh32Xn0pfsK3WkhRuh-yy4bp_AvaR56ZU6DXFxZ1jLAW1V4UiPqwzJgbtWDANYVVyJ77QSyYq0GXhy5cblgF1jG2gAZ-v2deEE78LO7lNKziEEKkmDsAisK1hAMb77QhHj3Fl_Qs024YCZtTyWSHtWG45nSMCCPWc9eYWcvkrjJR3qGRPEXD5N5Bvtcyzt30Z72hjNMbEA55n6m10yQAnIQvYLMWQpQD90Co4eb2oOE5YWZBMCTpluSv2ZoV"/>
                    </div>
                    <div class="p-6 flex flex-col flex-grow">
                        <h4 class="text-lg font-bold text-gray-800 dark:text-white mb-2">和室（6畳）</h4>
                        <p class="text-sm text-gray-600 dark:text-gray-300 mb-6 flex-grow leading-relaxed">
                            木のぬくもりを感じる伝統的な畳のお部屋です。ご家族連れにも人気です。
                        </p>
                        <a href="rooms.php" class="w-full py-2.5 px-4 rounded border border-primary text-primary hover:bg-primary hover:text-white dark:border-blue-400 dark:text-blue-400 dark:hover:bg-blue-400 dark:hover:text-gray-900 font-semibold transition-all duration-200 flex items-center justify-center gap-1 group-hover:gap-2">
                            詳細を見る
                            <span class="material-icons text-sm">arrow_forward</span>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
require_once 'includes/footer.php';
?>
