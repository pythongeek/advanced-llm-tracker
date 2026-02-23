/**
 * Admin JavaScript for Advanced LLM Tracker
 */

(function() {
    'use strict';

    const { createElement: e, Component, render } = wp.element;
    const { __ } = wp.i18n;
    const apiFetch = wp.apiFetch;

    // Dashboard Component
    class Dashboard extends Component {
        constructor(props) {
            super(props);
            this.state = {
                stats: null,
                loading: true,
                error: null,
                period: '24h'
            };
        }

        componentDidMount() {
            this.fetchStats();
        }

        fetchStats() {
            this.setState({ loading: true });
            
            apiFetch({
                path: `allmt/v1/stats?period=${this.state.period}`
            })
            .then(stats => {
                this.setState({ stats, loading: false });
            })
            .catch(error => {
                this.setState({ error, loading: false });
            });
        }

        render() {
            if (this.state.loading) {
                return e('div', { className: 'allmt-loading' },
                    e('div', { className: 'allmt-loading-spinner' })
                );
            }

            if (this.state.error) {
                return e('div', { className: 'allmt-error' },
                    __('Error loading data', 'advanced-llm-tracker')
                );
            }

            const { stats } = this.state;

            return e('div', { className: 'allmt-dashboard' },
                e('div', { className: 'allmt-stats-grid' },
                    e(StatCard, {
                        title: __('Total Sessions', 'advanced-llm-tracker'),
                        value: stats.sessions.total,
                        change: stats.sessions.bot_rate + '% bot rate'
                    }),
                    e(StatCard, {
                        title: __('Bot Sessions', 'advanced-llm-tracker'),
                        value: stats.sessions.bots,
                        change: stats.sessions.blocked + ' blocked'
                    }),
                    e(StatCard, {
                        title: __('Human Sessions', 'advanced-llm-tracker'),
                        value: stats.sessions.humans
                    }),
                    e(StatCard, {
                        title: __('Total Events', 'advanced-llm-tracker'),
                        value: stats.events.total
                    })
                ),
                e('div', { className: 'allmt-chart-container' },
                    e('h3', null, __('Traffic Overview', 'advanced-llm-tracker')),
                    e(TrafficChart, { data: stats.timeline })
                )
            );
        }
    }

    // Stat Card Component
    function StatCard({ title, value, change }) {
        return e('div', { className: 'allmt-stat-card' },
            e('h3', null, title),
            e('div', { className: 'allmt-stat-value' }, value.toLocaleString()),
            change && e('div', { className: 'allmt-stat-change' }, change)
        );
    }

    // Traffic Chart Component
    function TrafficChart({ data }) {
        const canvasRef = React.useRef(null);

        React.useEffect(() => {
            if (!canvasRef.current || !data) return;

            const ctx = canvasRef.current.getContext('2d');
            
            new Chart(ctx, {
                type: 'line',
                data: {
                    labels: data.map(d => d.time_period),
                    datasets: [{
                        label: __('Sessions', 'advanced-llm-tracker'),
                        data: data.map(d => d.total),
                        borderColor: '#2271b1',
                        backgroundColor: 'rgba(34, 113, 177, 0.1)',
                        fill: true
                    }, {
                        label: __('Bots', 'advanced-llm-tracker'),
                        data: data.map(d => d.bots),
                        borderColor: '#d63638',
                        backgroundColor: 'rgba(214, 54, 56, 0.1)',
                        fill: true
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'top'
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true
                        }
                    }
                }
            });
        }, [data]);

        return e('canvas', { 
            ref: canvasRef,
            style: { height: '300px' }
        });
    }

    // Initialize on DOM ready
    document.addEventListener('DOMContentLoaded', function() {
        // Dashboard
        const dashboardRoot = document.getElementById('allmt-dashboard-root');
        if (dashboardRoot) {
            render(e(Dashboard), dashboardRoot);
        }

        // Sessions
        const sessionsRoot = document.getElementById('allmt-sessions-root');
        if (sessionsRoot) {
            render(e(SessionsList), sessionsRoot);
        }

        // Alerts
        const alertsRoot = document.getElementById('allmt-alerts-root');
        if (alertsRoot) {
            render(e(AlertsList), alertsRoot);
        }
    });

    // Sessions List Component
    class SessionsList extends Component {
        constructor(props) {
            super(props);
            this.state = {
                sessions: [],
                loading: true,
                page: 1,
                perPage: 20
            };
        }

        componentDidMount() {
            this.fetchSessions();
        }

        fetchSessions() {
            const { page, perPage } = this.state;
            
            apiFetch({
                path: `allmt/v1/sessions?page=${page}&per_page=${perPage}`
            })
            .then(response => {
                this.setState({
                    sessions: response.sessions,
                    total: response.total,
                    loading: false
                });
            })
            .catch(error => {
                this.setState({ loading: false, error });
            });
        }

        render() {
            if (this.state.loading) {
                return e('div', { className: 'allmt-loading' },
                    e('div', { className: 'allmt-loading-spinner' })
                );
            }

            const { sessions, total, page, perPage } = this.state;

            if (sessions.length === 0) {
                return e('div', { className: 'allmt-empty-state' },
                    e('div', { className: 'allmt-empty-state-icon' }, '📊'),
                    e('p', null, __('No sessions found', 'advanced-llm-tracker'))
                );
            }

            return e('div', null,
                e('table', { className: 'allmt-data-table wp-list-table widefat fixed striped' },
                    e('thead', null,
                        e('tr', null,
                            e('th', null, __('Session ID', 'advanced-llm-tracker')),
                            e('th', null, __('Type', 'advanced-llm-tracker')),
                            e('th', null, __('Confidence', 'advanced-llm-tracker')),
                            e('th', null, __('Page Views', 'advanced-llm-tracker')),
                            e('th', null, __('Created', 'advanced-llm-tracker'))
                        )
                    ),
                    e('tbody', null,
                        sessions.map(session => e('tr', { key: session.session_id },
                            e('td', null, 
                                e('code', null, session.session_id.substring(0, 16) + '...')
                            ),
                            e('td', null,
                                e('span', { 
                                    className: `allmt-badge ${session.is_bot ? 'bot' : 'human'}`
                                }, session.is_bot ? __('Bot', 'advanced-llm-tracker') : __('Human', 'advanced-llm-tracker'))
                            ),
                            e('td', null, 
                                session.bot_confidence 
                                    ? (session.bot_confidence * 100).toFixed(1) + '%'
                                    : '-'
                            ),
                            e('td', null, session.page_views || 0),
                            e('td', null, new Date(session.created_at).toLocaleString())
                        ))
                    )
                ),
                e(Pagination, {
                    page,
                    perPage,
                    total,
                    onPageChange: (newPage) => this.setState({ page: newPage }, () => this.fetchSessions())
                })
            );
        }
    }

    // Alerts List Component
    class AlertsList extends Component {
        constructor(props) {
            super(props);
            this.state = {
                alerts: [],
                loading: true,
                page: 1,
                perPage: 20
            };
        }

        componentDidMount() {
            this.fetchAlerts();
        }

        fetchAlerts() {
            const { page, perPage } = this.state;
            
            apiFetch({
                path: `allmt/v1/alerts?page=${page}&per_page=${perPage}`
            })
            .then(response => {
                this.setState({
                    alerts: response.alerts,
                    total: response.total,
                    loading: false
                });
            })
            .catch(error => {
                this.setState({ loading: false, error });
            });
        }

        markAsRead(alertId) {
            apiFetch({
                path: `allmt/v1/alerts/${alertId}/read`,
                method: 'POST'
            })
            .then(() => {
                this.fetchAlerts();
            });
        }

        render() {
            if (this.state.loading) {
                return e('div', { className: 'allmt-loading' },
                    e('div', { className: 'allmt-loading-spinner' })
                );
            }

            const { alerts, total, page, perPage } = this.state;

            if (alerts.length === 0) {
                return e('div', { className: 'allmt-empty-state' },
                    e('div', { className: 'allmt-empty-state-icon' }, '📋'),
                    e('p', null, __('No alerts found', 'advanced-llm-tracker'))
                );
            }

            return e('div', null,
                e('table', { className: 'allmt-data-table wp-list-table widefat fixed striped' },
                    e('thead', null,
                        e('tr', null,
                            e('th', null, __('Severity', 'advanced-llm-tracker')),
                            e('th', null, __('Title', 'advanced-llm-tracker')),
                            e('th', null, __('Message', 'advanced-llm-tracker')),
                            e('th', null, __('Created', 'advanced-llm-tracker')),
                            e('th', null, __('Actions', 'advanced-llm-tracker'))
                        )
                    ),
                    e('tbody', null,
                        alerts.map(alert => e('tr', { 
                            key: alert.id,
                            style: { opacity: alert.is_read ? 0.6 : 1 }
                        },
                            e('td', null,
                                e('span', { 
                                    className: `allmt-badge ${alert.severity}`
                                }, alert.severity.toUpperCase())
                            ),
                            e('td', null, alert.title),
                            e('td', null, alert.message),
                            e('td', null, new Date(alert.created_at).toLocaleString()),
                            e('td', null,
                                !alert.is_read && e('button', {
                                    className: 'button button-small',
                                    onClick: () => this.markAsRead(alert.id)
                                }, __('Mark Read', 'advanced-llm-tracker'))
                            )
                        ))
                    )
                ),
                e(Pagination, {
                    page,
                    perPage,
                    total,
                    onPageChange: (newPage) => this.setState({ page: newPage }, () => this.fetchAlerts())
                })
            );
        }
    }

    // Pagination Component
    function Pagination({ page, perPage, total, onPageChange }) {
        const totalPages = Math.ceil(total / perPage);
        
        if (totalPages <= 1) return null;

        return e('div', { className: 'allmt-pagination' },
            e('button', {
                onClick: () => onPageChange(page - 1),
                disabled: page <= 1
            }, '‹ ' + __('Previous', 'advanced-llm-tracker')),
            
            e('span', { className: 'current' },
                sprintf(
                    /* translators: 1: Current page, 2: Total pages */
                    __('%1$d of %2$d', 'advanced-llm-tracker'),
                    page,
                    totalPages
                )
            ),
            
            e('button', {
                onClick: () => onPageChange(page + 1),
                disabled: page >= totalPages
            }, __('Next', 'advanced-llm-tracker') + ' ›')
        );
    }

})();
