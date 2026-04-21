// Script JavaScript pour les fonctionnalités avancées de trésorerie

class TreasuryManager {
    constructor() {
        this.init();
        this.setupEventListeners();
        this.setupKeyboardShortcuts();
    }

    init() {
        // Initialiser les tooltips
        this.initTooltips();
        
        // Charger les filtres sauvegardés
        this.loadSavedFilters();
        
        // Initialiser les animations
        this.initAnimations();
    }

    setupEventListeners() {
        // Écouteurs pour les formulaires
        document.getElementById('quickTransactionForm')?.addEventListener('submit', (e) => {
            e.preventDefault();
            this.saveQuickTransaction();
        });

        document.getElementById('filtersForm')?.addEventListener('submit', (e) => {
            e.preventDefault();
            this.applyFilters();
        });

        // Auto-refresh des données
        this.startAutoRefresh();
    }

    setupKeyboardShortcuts() {
        document.addEventListener('keydown', (e) => {
            // Ctrl/Cmd + N: Nouvelle transaction
            if ((e.ctrlKey || e.metaKey) && e.key === 'n') {
                e.preventDefault();
                this.quickTransaction('encaissement');
            }
            
            // Ctrl/Cmd + E: Exporter
            if ((e.ctrlKey || e.metaKey) && e.key === 'e') {
                e.preventDefault();
                this.exportData();
            }
            
            // Ctrl/Cmd + F: Recherche
            if ((e.ctrlKey || e.metaKey) && e.key === 'f') {
                e.preventDefault();
                document.getElementById('quickSearch')?.focus();
            }
            
            // Échap: Fermer les modals
            if (e.key === 'Escape') {
                this.closeAllModals();
            }
        });
    }

    // Transaction rapide
    quickTransaction(type) {
        document.getElementById('quickType').value = type;
        const modal = new bootstrap.Modal(document.getElementById('quickTransactionModal'));
        modal.show();
        
        // Mettre à jour le titre
        const title = document.querySelector('#quickTransactionModal .modal-title');
        title.textContent = type === 'encaissement' ? 'Nouvel encaissement' : 'Nouveau décaissement';
        
        // Adapter les options selon le type
        this.updateTransactionOptions(type);
    }

    updateTransactionOptions(type) {
        const select = document.querySelector('select[name="transaction_type"]');
        const options = select.querySelectorAll('optgroup');
        
        options.forEach(optgroup => {
            if (type === 'encaissement') {
                optgroup.style.display = optgroup.label.includes('Encaissements') ? 'block' : 'none';
            } else {
                optgroup.style.display = optgroup.label.includes('Décaissements') ? 'block' : 'none';
            }
        });
        
        // Sélectionner la première option visible
        const firstVisible = select.querySelector('option:not([disabled]):not([style*="display: none"])');
        if (firstVisible) {
            firstVisible.selected = true;
        }
    }

    async saveQuickTransaction() {
        const form = document.getElementById('quickTransactionForm');
        const formData = new FormData(form);
        
        try {
            const response = await fetch('/treasury', {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'Accept': 'application/json',
                },
                body: formData
            });
            
            if (response.ok) {
                this.showNotification('Transaction ajoutée avec succès!', 'success');
                bootstrap.Modal.getInstance(document.getElementById('quickTransactionModal')).hide();
                form.reset();
                this.refreshData();
            } else {
                throw new Error('Erreur lors de l\'ajout de la transaction');
            }
        } catch (error) {
            this.showNotification(error.message, 'error');
        }
    }

    // Filtres
    applyFilters() {
        const formData = new FormData(document.getElementById('filtersForm'));
        const params = new URLSearchParams(formData);
        
        // Rediriger avec les filtres
        window.location.href = `/treasury/tracking?${params.toString()}`;
    }

    resetFilters() {
        document.getElementById('filtersForm').reset();
        this.applyFilters();
    }

    saveFilters() {
        const formData = new FormData(document.getElementById('filtersForm'));
        const filters = Object.fromEntries(formData);
        
        localStorage.setItem('treasuryFilters', JSON.stringify(filters));
        this.showNotification('Filtres sauvegardés!', 'success');
    }

    loadSavedFilters() {
        const saved = localStorage.getItem('treasuryFilters');
        if (saved) {
            const filters = JSON.parse(saved);
            const form = document.getElementById('filtersForm');
            
            Object.entries(filters).forEach(([key, value]) => {
                const input = form.querySelector(`[name="${key}"]`);
                if (input) {
                    input.value = value;
                }
            });
        }
    }

    // Recherche rapide
    quickSearch() {
        const searchTerm = document.getElementById('quickSearch').value.toLowerCase();
        const rows = document.querySelectorAll('tbody tr');
        
        rows.forEach(row => {
            const text = row.textContent.toLowerCase();
            row.style.display = text.includes(searchTerm) ? '' : 'none';
        });
    }

    clearSearch() {
        document.getElementById('quickSearch').value = '';
        this.quickSearch();
    }

    // Export
    async exportData() {
        try {
            const response = await fetch('/treasury/export/csv', {
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                }
            });
            
            if (response.ok) {
                const blob = await response.blob();
                const url = window.URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                a.download = `tresorerie_${new Date().toISOString().split('T')[0]}.csv`;
                a.click();
                window.URL.revokeObjectURL(url);
                
                this.showNotification('Export CSV téléchargé!', 'success');
            }
        } catch (error) {
            this.showNotification('Erreur lors de l\'export', 'error');
        }
    }

    // Rapport PDF
    async generateReport() {
        try {
            this.showNotification('Génération du rapport en cours...', 'info');
            
            const response = await fetch('/treasury/report/pdf', {
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                }
            });
            
            if (response.ok) {
                const blob = await response.blob();
                const url = window.URL.createObjectURL(blob);
                window.open(url, '_blank');
                window.URL.revokeObjectURL(url);
                
                this.showNotification('Rapport PDF généré!', 'success');
            }
        } catch (error) {
            this.showNotification('Erreur lors de la génération du rapport', 'error');
        }
    }

    // Notifications
    showNotification(message, type = 'info') {
        const notification = document.createElement('div');
        notification.className = `alert alert-${type} alert-dismissible fade show position-fixed`;
        notification.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 300px;';
        notification.innerHTML = `
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        `;
        
        document.body.appendChild(notification);
        
        // Auto-suppression après 5 secondes
        setTimeout(() => {
            if (notification.parentNode) {
                notification.remove();
            }
        }, 5000);
    }

    // Utilitaires
    closeAllModals() {
        document.querySelectorAll('.modal.show').forEach(modal => {
            bootstrap.Modal.getInstance(modal)?.hide();
        });
    }

    refreshData() {
        // Recharger la page actuelle
        window.location.reload();
    }

    startAutoRefresh() {
        // Rafraîchir les données toutes les 5 minutes
        setInterval(() => {
            if (document.visibilityState === 'visible') {
                this.refreshData();
            }
        }, 5 * 60 * 1000);
    }

    initTooltips() {
        // Initialiser les tooltips Bootstrap
        const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });
    }

    initAnimations() {
        // Animer les nombres
        this.animateNumbers();
        
        // Animer les cartes au scroll
        this.setupScrollAnimations();
    }

    animateNumbers() {
        const numbers = document.querySelectorAll('[data-animate-number]');
        
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    const target = entry.target;
                    const finalValue = parseFloat(target.textContent.replace(/[^0-9.-]/g, ''));
                    this.animateValue(target, 0, finalValue, 2000);
                    observer.unobserve(target);
                }
            });
        });
        
        numbers.forEach(number => observer.observe(number));
    }

    animateValue(element, start, end, duration) {
        const range = end - start;
        const increment = range / (duration / 16);
        let current = start;
        
        const timer = setInterval(() => {
            current += increment;
            if ((increment > 0 && current >= end) || (increment < 0 && current <= end)) {
                current = end;
                clearInterval(timer);
            }
            
            element.textContent = this.formatNumber(current);
        }, 16);
    }

    formatNumber(num) {
        return new Intl.NumberFormat('fr-FR', {
            minimumFractionDigits: 0,
            maximumFractionDigits: 0
        }).format(num);
    }

    setupScrollAnimations() {
        const cards = document.querySelectorAll('.card-summary');
        
        const observer = new IntersectionObserver((entries) => {
            entries.forEach((entry, index) => {
                if (entry.isIntersecting) {
                    setTimeout(() => {
                        entry.target.style.opacity = '1';
                        entry.target.style.transform = 'translateY(0)';
                    }, index * 100);
                }
            });
        });
        
        cards.forEach(card => {
            card.style.opacity = '0';
            card.style.transform = 'translateY(20px)';
            card.style.transition = 'all 0.6s ease';
            observer.observe(card);
        });
    }
}

// Fonctions globales pour la compatibilité
function quickTransaction(type) {
    window.treasuryManager.quickTransaction(type);
}

function saveQuickTransaction() {
    window.treasuryManager.saveQuickTransaction();
}

function applyFilters() {
    window.treasuryManager.applyFilters();
}

function resetFilters() {
    window.treasuryManager.resetFilters();
}

function saveFilters() {
    window.treasuryManager.saveFilters();
}

function quickSearch() {
    window.treasuryManager.quickSearch();
}

function clearSearch() {
    window.treasuryManager.clearSearch();
}

function exportData() {
    window.treasuryManager.exportData();
}

function generateReport() {
    window.treasuryManager.generateReport();
}

// Initialiser au chargement du DOM
document.addEventListener('DOMContentLoaded', () => {
    window.treasuryManager = new TreasuryManager();
});
