<template>
    <!-- Menu version desktop -->
    <aside :class="[
        'print:hidden bg-gradient-to-b from-sky-800 via-sky-700 to-sky-800 text-white h-screen fixed left-0 top-0 z-40 transition-all duration-300 shadow-2xl border-r border-sky-600/40',
        isOpen && !isMobile ? 'w-64' : (isMobile ? 'w-64' : 'w-20'),
        isMobile && !isOpen ? '-translate-x-full' : 'translate-x-0'
    ]">
        <div class="flex flex-col h-full">
            <!-- Logo et titre -->
            <div class="flex items-center justify-between p-3 border-b border-white/15">
                <!-- Logo toujours visible -->
                <div class="flex items-center overflow-hidden">
                    <img v-if="logoExists" :src="logoUrl" alt="Gendarmerie Nationale"
                        class="h-9 w-9 object-contain rounded-full bg-white p-0.5 transition-all duration-300 shrink-0 shadow-sm" />
                    <!-- Titre visible uniquement quand menu ouvert OU sur mobile -->
                    <h3 v-if="(isOpen || !isMobile) && !isMobile"
                        class="text-white font-bold text-base ml-2 whitespace-nowrap tracking-wide" translate="no">
                        Gendarmerie
                    </h3>
                    <h3 v-if="isMobile" class="text-white font-bold text-base ml-2 whitespace-nowrap tracking-wide" translate="no">
                        Gendarmerie
                    </h3>
                </div>

                <!-- Bouton toggle (caché sur mobile) -->
                <button v-if="!isMobile" @click="toggleSidebar"
                    class="bg-white/15 rounded-full p-1 text-white hover:bg-white/25 transition-colors shrink-0 ml-2">
                    <i :class="isOpen ? 'pi pi-chevron-left' : 'pi pi-chevron-right'" class="text-xs"></i>
                </button>

                <!-- Bouton fermeture mobile -->
                <button v-if="isMobile" @click="closeMobileMenu"
                    class="bg-white/15 rounded-full p-1 text-white hover:bg-white/25 transition-colors shrink-0 ml-2">
                    <i class="pi pi-times text-xs"></i>
                </button>
            </div>

            <!-- Navigation -->
            <nav class="flex-1 overflow-y-auto py-2 px-2 no-scrollbar" style="scrollbar-width: none; -ms-overflow-style: none;">
                <ul class="space-y-0.5">
                    <li v-for="item in menuItems" :key="item.name" class="relative group">
                        <NavLink :href="item.href" :active="isActive(item.href)" @click="handleNavClick">
                            <template #default>
                                <div class="flex items-center justify-between w-full px-2.5 py-1.5">
                                    <div class="flex items-center">
                                        <!-- Icône toujours visible -->
                                        <i :class="item.icon + ' text-lg w-5 shrink-0'"></i>

                                        <!-- Texte : visible sur mobile ou quand menu ouvert -->
                                        <span v-if="isMobile || isOpen" class="ml-2.5 whitespace-nowrap text-sm">
                                            {{ item.name }}
                                        </span>

                                        <!-- Tooltip au survol quand menu réduit (desktop uniquement) -->
                                        <div v-if="!isMobile && !isOpen"
                                            class="absolute left-full ml-2 px-2 py-1 bg-gray-800 text-white text-xs rounded whitespace-nowrap opacity-0 group-hover:opacity-100 transition-opacity pointer-events-none z-50">
                                            {{ item.name }}
                                        </div>
                                    </div>

                                    <!-- Badge alertes -->
                                    <span v-if="item.name === 'Alertes' && alertesCount > 0 && (isMobile || isOpen)"
                                        class="bg-red-500 text-white text-xs font-bold px-1.5 py-0.5 rounded-full ml-2 shrink-0">
                                        {{ alertesCount > 99 ? '99+' : alertesCount }}
                                    </span>
                                </div>
                            </template>
                        </NavLink>
                    </li>
                </ul>
            </nav>

            <!-- Déconnexion -->
            <div class="border-t border-white/15 p-2.5">
                <button @click="logout"
                    class="w-full flex items-center text-white hover:bg-white/15 rounded-lg px-2.5 py-1.5 transition-colors relative group">
                    <i class="pi pi-sign-out text-lg shrink-0"></i>

                    <!-- Texte : visible sur mobile ou quand menu ouvert -->
                    <span v-if="isMobile || isOpen" class="ml-2.5 whitespace-nowrap text-sm">Déconnexion</span>

                    <!-- Tooltip au survol quand menu réduit (desktop uniquement) -->
                    <div v-if="!isMobile && !isOpen"
                        class="absolute left-full ml-2 px-2 py-1 bg-gray-800 text-white text-xs rounded whitespace-nowrap opacity-0 group-hover:opacity-100 transition-opacity pointer-events-none z-50">
                        Déconnexion
                    </div>
                </button>
            </div>
        </div>
    </aside>

    <!-- Overlay pour mobile -->
    <div v-if="isMobile && isOpen" @click="closeMobileMenu"
        class="fixed inset-0 bg-black bg-opacity-50 z-30 transition-opacity duration-300"></div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import NavLink from './NavLink.vue';

const props = defineProps({
    isOpen: {
        type: Boolean,
        default: false
    },
    isMobile: {
        type: Boolean,
        default: false
    }
});

const emit = defineEmits(['update:isOpen', 'closeMobile']);

const page = usePage();
const alertesCount = computed(() => page.props.alertesCount || 0);

// Logo Gendarmerie
const logoExists = ref(true);
const logoUrl = ref('/images/logo-gendarmerie-officiel.png?v=2026');

const toggleSidebar = () => {
    emit('update:isOpen', !props.isOpen);
};

const closeMobileMenu = () => {
    emit('closeMobile');
};

const handleNavClick = () => {
    if (props.isMobile) {
        closeMobileMenu();
    }
};

const menuItems = computed(() => {
    if (page.props.auth.user.role === 1) {
        return [
            { name: 'Utilisateurs', href: '/users', icon: 'pi pi-users' },
            { name: 'Journal d\'actività©s', href: '/activity-logs', icon: 'pi pi-history' }
        ];
    } else {
        return [
            { name: 'Tableau de bord', href: '/dashboard', icon: 'pi pi-chart-line' },
            { name: 'Carte opérationnelle', href: '/carte', icon: 'pi pi-map' },
            { name: 'Militaires', href: '/militaires', icon: 'pi pi-users' },
            { name: 'Alertes', href: '/alertes', icon: 'pi pi-bell' },
            { name: 'Éligibilités', href: '/eligibilites', icon: 'pi pi-check-circle' },
            { name: 'Avancement', href: '/avancements', icon: 'pi pi-sort-amount-down' },
            { name: 'Contrats', href: '/contrats', icon: 'pi pi-file' },
            { name: 'Grades', href: '/grades', icon: 'pi pi-star' },
            { name: 'Certificats', href: '/certificats', icon: 'pi pi-file-pdf' },
            { name: 'Configuration', href: '/configurations', icon: 'pi pi-cog' }
        ];
    }
});

const isActive = (href) => {
    return window.location.pathname === href || window.location.pathname.startsWith(href + '/');
};

const logout = () => {
    router.post(route('logout'));
};
</script>

<style scoped>
.no-scrollbar {
    -ms-overflow-style: none !important;
    scrollbar-width: none !important;
}
.no-scrollbar::-webkit-scrollbar {
    display: none !important;
    width: 0 !important;
    height: 0 !important;
}

.sidebar-scroll::-webkit-scrollbar {
    display: none !important;
    width: 0 !important;
}

.sidebar-scroll::-webkit-scrollbar-track {
    background: #0284c7;
}

.sidebar-scroll::-webkit-scrollbar-thumb {
    background: #0e7490;
    border-radius: 5px;
}

.sidebar-scroll::-webkit-scrollbar-thumb:hover {
    background: #155e75;
}

.pi {
    font-size: 1.25rem;
}

.group-hover\:opacity-100 {
    opacity: 1;
}

.opacity-0 {
    opacity: 0;
}

.transition-opacity {
    transition-property: opacity;
    transition-timing-function: cubic-bezier(0.4, 0, 0.2, 1);
    transition-duration: 150ms;
}

.whitespace-nowrap {
    white-space: nowrap;
}

.shrink-0 {
    flex-shrink: 0;
}

.overflow-hidden {
    overflow: hidden;
}
</style>

