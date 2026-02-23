/**
 * Admin JavaScript for Advanced LLM Tracker
 */

(function(wp) {
    'use strict';

    const { createElement: el, render, useState, useEffect } = wp.element;
    const { __ } = wp.i18n;
    const apiFetch = wp.apiFetch;

    // Dashboard Component
    function Dashboard() {
        const [stats, setStats] = useState(null);
        const [loading, setLoading] = useState(true);
        const [error, setError] = useState(null);

        useEffect(() => {
            fetchStats();
        }, []);

        const fetchStats = async () => {
            try {
                const response = await apiFetch({
                    path: 'allmt/v1/stats?period=24h',
                    headers: {
                        'X-WP-Nonce': ALLMT_ADMIN.nonce
                    }
                });
                setStats(response);
                setLoading(false);
            } catch (err) {
                setError(err.message);
                setLoading(false);
            }
        };

        if (loading) {
            return el('div', { className: 'allmt-loading' },
                el('div', { className: 'allmt-loading-spinner' }),
                el('p', null, ALLMT_ADMIN.strings.loading)
            );
        }

        if (error) {
            return el('div', { className: 'allmt-alert allmt-alert-error' },
                ALLMT_ADMIN.strings.error + ': ' + error
            );
        }

        return el('div', { className: 'allmt-dashboard' },
            el('div', { className: 'allmt-stats-grid' },
                el(StatCard, {
                    title: __('Total Sessions', 'advanced-llm-tracker'),
                    value: stats.total_sessions,
                    change: '+12%'
                }),
                el(StatCard, {
                    title: __('Bot Sessions', 'advanced-llm-tracker'),
                    value: stats.bot_sessions,
                    change: stats.bot_percentage + '%'
                }),
                el(StatCard, {
                    title: __('Blocked', 'advanced-llm-tracker'),
                    value: stats.blocked_sessions,
                    change: '-5%'
                }),
                el(StatCard, {
                    title: __('Alerts', 'advanced-llm-tracker'),
                    value: stats.alerts,
                    change: '+3'
                })
            ),
            el('div', { className: 'allmt-chart-container' },
                el('h2', null, __('Traffic Overview', 'advanced-llm-tracker')),
                el('canvas', { id: 'allmt-traffic-chart' })
            )
        );
    }

    // Stat Card Component
    function StatCard({ title, value, change }) {
        const changeClass = change && change.startsWith('+') ? 'positive' : 
                           (change && change.startsWith('-') ? 'negative' : '');
        
        return el('div', { className: 'allmt-stat-card' },
            el('h3', null, title),
            el('p', { className: 'allmt-stat-value' }, value !== undefined ? value.toLocaleString() : '0'),
            change && el('p', { className: 'allmt-stat-change ' + changeClass }, change)
        );
    }

    // Initialize dashboard
    document.addEventListener('DOMContentLoaded', function() {
        const dashboardRoot = document.getElementById('allmt-dashboard-root');
        if (dashboardRoot) {
            render(el(Dashboard), dashboardRoot);
        }
    });

})(window.wp);
