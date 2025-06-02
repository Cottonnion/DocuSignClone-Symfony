// Sidebar dropdown logic
// Refactored as a class with nonce support for AJAX requests security

class SidebarDropdown {
    /**
     * Initialize the sidebar dropdown functionality.
     * @param {Object} options - Configuration options.
     * @param {string} options.ajaxUrl - The AJAX URL for backend requests.
     * @param {string} options.nonce - The security nonce for AJAX requests.
     */
    constructor({ ajaxUrl, nonce }) {
        this.ajaxUrl = ajaxUrl;
        this.nonce = nonce;
        this.select = document.querySelector('.main-collection-select');
        this.groupPanel = document.querySelector('.group-components-panel');
        this.cache = {
            groups: new Map(),
            components: new Map()
        };
        this.currentMenuType = null;
        this.isAdmin = false;
        this.schoolCount = 0;
        this.totalGroupsCount = 0;
        this.singleSchoolData = null;
        
        // Create the group panel if it doesn't exist
        if (!this.groupPanel) {
            console.log('Creating missing group components panel');
            this.groupPanel = document.createElement('div');
            this.groupPanel.className = 'group-components-panel';
            
            // Find the appropriate container to append to
            const sidebar = document.querySelector('.sidebar-sub-menu');
            if (sidebar) {
                sidebar.appendChild(this.groupPanel);
            } else {
                // Try other container options
                const altContainer = document.querySelector('.main-collection-wrap') || 
                                    document.querySelector('.sidebar-nav-group') || 
                                    document.querySelector('.sidebar-content-wrapper');
                if (altContainer) {
                    altContainer.appendChild(this.groupPanel);
                } else {
                    // Last resort - append to body
                    document.body.appendChild(this.groupPanel);
                }
            }
        }
        
        // Ensure components panel is visible and has proper styling on init
        if (this.groupPanel) {
            this.groupPanel.style.display = 'block';
            this.groupPanel.style.margin = '10px 0';
            this.groupPanel.style.padding = '0';
            this.groupPanel.innerHTML = ''; // Clear any existing content to show empty state
        }
        
        this.init();
    }

    /**
     * Check if user is admin and get school count
     */
    async checkUserAccess() {
        // Immediately check if user is admin from DOM
        this.isAdmin = document.body.classList.contains('user-is-admin') || 
                      document.body.classList.contains('administrator');
        
        // If user is admin, no need to proceed further
        if (this.isAdmin) {
            return;
        }

        try {
            const data = await this.getCachedData('groups', 'school', async () => {
                const params = new URLSearchParams({
                    action: 'get_bp_groups',
                    menu: 'school',
                    nonce: this.nonce
                });
                const response = await fetch(this.ajaxUrl + '?' + params.toString());
                return response.json();
            });
            
            if (data.success) {
                this.schoolCount = data.data.groups.length;
                // Store the total group count across all types (school, program, cohort)
                this.totalGroupsCount = data.data.total_groups_count || data.data.groups.length;
                
                console.log('School API response:', { 
                    schoolCount: this.schoolCount,
                    totalGroupsCount: this.totalGroupsCount, 
                    groups: data.data.groups
                });
                
                // CRITICAL: Check if user has exactly one group total
                if (this.totalGroupsCount === 1) {
                    console.log('User has exactly one group, using simplified single-group mode');
                    
                    // If we have schools, handle that case
                    if (this.schoolCount === 1) {
                        this.singleSchoolData = data.data.groups[0];
                        this.createSingleGroupComponents(this.singleSchoolData);
                        this.updateUIForAccess();
                    } else {
                        // User has no schools but has one group of another type
                        // We need to find which type it is (program or cohort)
                        await this.findAndSetSingleGroup();
                    }
                } 
                // Handle case where schoolCount is 0 but totalGroupsCount > 0
                else if ((this.schoolCount === 0 && this.totalGroupsCount > 0) || 
                        (data.data.total_groups_count > 0 && data.data.groups.length === 0)) {
                    // User has no schools but has groups of another type
                    // OR API returned empty groups but indicated groups exist
                    // We need to find which type it is (program or cohort)
                    await this.findAndSetSingleGroup();
                } else {
                    // Multiple groups or no groups at all
                    this.updateUIForAccess();
                }
            }
        } catch (error) {
            console.error('Error checking user access:', error);
        }
    }
    
    /**
     * Find the single group a user has access to if it's not a school
     */
    async findAndSetSingleGroup() {
        console.log('Finding single group for user...');
        // Try cohort first since that's more likely for students
        try {
            // Check cohort groups first (many students are only in cohorts)
            const cohortData = await this.getCachedData('groups', 'cohort', async () => {
                const params = new URLSearchParams({
                    action: 'get_bp_groups',
                    menu: 'cohort',
                    nonce: this.nonce
                });
                const response = await fetch(this.ajaxUrl + '?' + params.toString());
                return response.json();
            });
            
            console.log('Cohort API response:', cohortData);
            
            if (cohortData.success && cohortData.data.groups && cohortData.data.groups.length > 0) {
                // If there's exactly one cohort, use it
                if (cohortData.data.groups.length === 1) {
                    this.singleSchoolData = cohortData.data.groups[0];
                    this.currentMenuType = 'cohort';
                    this.createSingleGroupComponents(this.singleSchoolData);
                    this.updateUIForAccess();
                    return;
                } else if (cohortData.data.groups.length > 1) {
                    // Multiple cohorts, don't set a single one
                    return;
                }
            }
            
            // If no cohorts or multiple cohorts, check program groups
            const programData = await this.getCachedData('groups', 'program', async () => {
                const params = new URLSearchParams({
                    action: 'get_bp_groups',
                    menu: 'program',
                    nonce: this.nonce
                });
                const response = await fetch(this.ajaxUrl + '?' + params.toString());
                return response.json();
            });
            
            console.log('Program API response:', programData);
            
            if (programData.success && programData.data.groups && programData.data.groups.length === 1) {
                this.singleSchoolData = programData.data.groups[0];
                this.currentMenuType = 'program';
                this.createSingleGroupComponents(this.singleSchoolData);
                this.updateUIForAccess();
                return;
            }
            
            // If we got here, we couldn't find a single group to auto-select
            this.updateUIForAccess();
        } catch (error) {
            console.error('Error finding single group:', error);
            this.updateUIForAccess();
        }
    }

    /**
     * Update UI based on user access
     */
    updateUIForAccess() {
        // If user is admin or has more than one total group, do nothing
        if (this.isAdmin || this.totalGroupsCount > 1) {
            return;
        }

        // Only proceed if user has exactly one total group and is not admin
        const menuLinks = document.querySelectorAll('.sidebar-main-link');
        menuLinks.forEach(link => {
            // Add class for single school styling
            link.classList.add('empty-circle');
        });

        // Hide the dropdown
        if (this.select) {
            this.select.style.display = 'none';
        }

        // Show group components panel
        const componentsPanel = document.querySelector('.group-components-panel');
        if (componentsPanel) {
            componentsPanel.style.display = 'block';
        }
        
        // Hide sidebar-main-navs for students
        if (document.body.classList.contains('student-role') || 
            document.querySelector('.student-profile') !== null) {
            const sidebarMainNavs = document.querySelector('.sidebar-main-navs');
            if (sidebarMainNavs) {
                sidebarMainNavs.style.display = 'none';
            }
        }

        // Show school name but don't load components as we now do that in createSingleGroupComponents
        this.showSingleSchoolName();
    }

    /**
     * Show just the school name without loading components
     * Components are now loaded in createSingleGroupComponents
     */
    showSingleSchoolName() {
        try {
            // Use the stored group data
            if (this.singleSchoolData) {
                const group = this.singleSchoolData;
                
                // Remove any existing group name heading
                const oldHeading = document.querySelector('.single-school-name');
                if (oldHeading) oldHeading.remove();

                // Create group name heading (h2)
                const groupNameHeading = document.createElement('h2');
                groupNameHeading.className = 'single-school-name student-component-title';
                groupNameHeading.textContent = group.name;
                
                // Add heading above the components panel
                const componentsPanel = document.querySelector('.group-components-panel');
                if (componentsPanel) {
                    // Create a wrapper for the title if it doesn't exist
                    let titleWrapper = document.querySelector('.student-component-title-wrapper');
                    if (!titleWrapper) {
                        titleWrapper = document.createElement('div');
                        titleWrapper.className = 'student-component-title-wrapper';
                        // titleWrapper.style.textAlign = 'center';
                        titleWrapper.style.padding = '10px 5px';
                        
                        // Insert before the components panel
                        componentsPanel.parentNode.insertBefore(titleWrapper, componentsPanel);
                    }
                    
                    // Add the heading to the wrapper
                    titleWrapper.innerHTML = '';
                    titleWrapper.appendChild(groupNameHeading);
                } else if (this.select && this.select.parentNode) {
                    // Fallback to replacing the select
                    this.select.replaceWith(groupNameHeading);
                }
            }
        } catch (error) {
            console.error('Error showing single group name:', error);
        }
    }

    /**
     * Save current state to localStorage
     * @param {string} menuType - The type of menu (school, program, cohort)
     * @param {string} groupId - The selected group ID
     * @param {string} componentUrl - The selected component URL
     */
    saveState(menuType, groupId, componentUrl) {
        // Only save state if we have valid data
        if (menuType && groupId && groupId !== 'Select School...') {
            localStorage.setItem('sidebarMenuType', menuType);
            localStorage.setItem('sidebarGroupId', groupId);
            localStorage.setItem('sidebarComponentUrl', componentUrl);
        }
    }

    /**
     * Get saved state from localStorage
     * @returns {Object} The saved state
     */
    getSavedState() {
        return {
            menuType: localStorage.getItem('sidebarMenuType'),
            groupId: localStorage.getItem('sidebarGroupId'),
            componentUrl: localStorage.getItem('sidebarComponentUrl')
        };
    }

    /**
     * Get cached data or fetch from server, with 2-hour localStorage cache
     * @param {string} type - The type of data to fetch ('groups' or 'components')
     * @param {string} key - The cache key
     * @param {Function} fetchFn - The function to fetch data if not cached
     * @returns {Promise} The data promise
     */
    async getCachedData(type, key, fetchFn) {
        // Don't cache invalid keys
        if (key === 'Select School...' || !key) {
            return await fetchFn();
        }

        const cache = this.cache[type];
        const localKey = `sidebarCache_${type}_${key}`;
        const localCacheRaw = localStorage.getItem(localKey);
        const now = Date.now();
        const maxAge = 2 * 60 * 60 * 1000; // 2 hours in ms
        if (localCacheRaw) {
            try {
                const localCache = JSON.parse(localCacheRaw);
                if (localCache.timestamp && (now - localCache.timestamp < maxAge)) {
                    // Also update in-memory cache for consistency
                    cache.set(key, localCache.data);
                    return localCache.data;
                } else {
                    localStorage.removeItem(localKey);
                }
            } catch (e) {
                localStorage.removeItem(localKey);
            }
        }
        if (cache.has(key)) {
            return cache.get(key);
        }
        const data = await fetchFn();
        
        // Check if we have valid data before caching
        if (data && data.success && data.data) {
            // For groups, check if we have actual groups
            if (type === 'groups' && (!data.data.groups || data.data.groups.length === 0)) {
                return data;
            }
            // For components, check if we have actual components
            if (type === 'components' && (!data.data.components || data.data.components.length === 0)) {
                return data;
            }
            // Only cache if we have valid data
            cache.set(key, data);
            localStorage.setItem(localKey, JSON.stringify({ data, timestamp: now }));
        }
        return data;
    }

    /**
     * Fetch and render group options for a given menu type.
     * @param {string} menuType - The type of menu (school, program, cohort).
     * @param {string} [selectedGroupId] - Optional group ID to select after loading
     */
    async fetchAndRenderGroups(menuType, selectedGroupId) {
        if (!this.select) return;
        
        /*
        // If we have cached data and no selectedGroupId, use it immediately
        if (this.cache.groups.has(menuType) && !selectedGroupId) {
            console.log('Using cached groups data');
            this.renderGroups(this.cache.groups.get(menuType), selectedGroupId);
            return;
        }
        */

        this.select.innerHTML = '';
        const loadingOption = document.createElement('option');
        loadingOption.textContent = 'Loading...';
        loadingOption.disabled = true;
        loadingOption.selected = true;
        this.select.appendChild(loadingOption);

        try {
            const data = await this.getCachedData('groups', menuType, async () => {
                const params = new URLSearchParams({
                    action: 'get_bp_groups',
                    menu: menuType,
                    nonce: this.nonce
                });
                const response = await fetch(this.ajaxUrl + '?' + params.toString());
                return response.json();
            });

            this.renderGroups(data, selectedGroupId);
        } catch (error) {
            console.error('Error fetching groups:', error);
            this.select.innerHTML = '';
            const errorOption = document.createElement('option');
            errorOption.textContent = 'Error loading data.';
            errorOption.disabled = true;
            errorOption.selected = true;
            this.select.appendChild(errorOption);
        }
    }

    /**
     * Render groups in the select element
     * @param {Object} data - The groups data
     * @param {string} [selectedGroupId] - Optional group ID to select
     */
    renderGroups(data, selectedGroupId) {
        this.select.innerHTML = '';
        if (data.success && data.data.groups.length > 0) {
            const defaultOption = document.createElement('option');
            defaultOption.textContent = `Select ${this.currentMenuType.charAt(0).toUpperCase() + this.currentMenuType.slice(1)}...`;
            defaultOption.disabled = true;
            defaultOption.selected = true;
            this.select.appendChild(defaultOption);
            data.data.groups.forEach(group => {
                const option = document.createElement('option');
                option.value = group.id || group.url;
                option.textContent = group.name;
                option.setAttribute('data-url', group.url);
                this.select.appendChild(option);
            });

            // Auto-select if only one group or if selectedGroupId is provided
            if (selectedGroupId) {
                this.select.value = selectedGroupId;
                this.select.dispatchEvent(new Event('change'));
            } else if (data.data.groups.length === 1) {
                // Auto-select the single group if there's only one
                this.select.value = data.data.groups[0].id || data.data.groups[0].url;
                this.select.dispatchEvent(new Event('change'));
                console.log(`Auto-selected the only ${this.currentMenuType} available: ${data.data.groups[0].name}`);
            }
        } else {
            const noOption = document.createElement('option');
            noOption.textContent = 'No items found.';
            noOption.disabled = true;
            noOption.selected = true;
            this.select.appendChild(noOption);
        }
    }

    /**
     * Load components for a selected group
     * @param {string} menuType - The type of menu (school, program, cohort)
     * @param {string} groupId - The selected group ID
     */
    async loadComponents(menuType, groupId) {
        if (!groupId) {
            console.error('Cannot load components: Missing groupId');
            return;
        }
        
        // Create the panel if it doesn't exist
        if (!this.groupPanel) {
            console.log('Creating group panel in loadComponents');
            this.groupPanel = document.createElement('div');
            this.groupPanel.className = 'group-components-panel';
            
            // Find the appropriate container to append to
            const sidebar = document.querySelector('.sidebar-sub-menu');
            if (sidebar) {
                sidebar.appendChild(this.groupPanel);
            } else {
                // Try other container options
                const altContainer = document.querySelector('.main-collection-wrap') || 
                                    document.querySelector('.sidebar-nav-group') || 
                                    document.querySelector('.sidebar-content-wrapper');
                if (altContainer) {
                    altContainer.appendChild(this.groupPanel);
                } else {
                    // Last resort - append to body
                    document.body.appendChild(this.groupPanel);
                }
            }
        }
        
        if (!this.groupPanel) {
            console.error('Cannot load components: Failed to create groupPanel element');
            return;
        }
        
        console.log(`Loading components for ${menuType} group: ${groupId}`);
        
        // Always save state when loading components, whether cached or not
        this.saveState(menuType, groupId, '');
        
        /*
        // If we have cached components, use them immediately
        if (this.cache.components.has(groupId)) {
            const cachedData = this.cache.components.get(groupId);
            this.renderComponents(cachedData);
            return;
        }
        */

        this.groupPanel.style.display = 'block';
        this.groupPanel.classList.add('loading');
        
        try {
            const data = await this.getCachedData('components', groupId, async () => {
                const params = new URLSearchParams({
                    action: 'get_group_components',
                    group_id: groupId,
                    nonce: this.nonce
                });
                console.log(`Fetching components for group ${groupId} with URL:`, this.ajaxUrl + '?' + params.toString());
                const response = await fetch(this.ajaxUrl + '?' + params.toString());
                return response.json();
            });

            console.log('Components data:', data);
            this.renderComponents(data);
        } catch (error) {
            console.error('Error loading components:', error);
            this.groupPanel.innerHTML = '<div style="padding:10px 22px;color:#c00;">Error loading components.</div>';
        } finally {
            this.groupPanel.classList.remove('loading');
        }
    }

    /**
     * Render components in the panel
     * @param {Object} data - The components data
     */
    renderComponents(data) {
        // Check if we need to generate student fallback components
        const needsFallbackComponents = data.success && 
                                      (!data.data.components || 
                                       !Array.isArray(data.data.components) || 
                                       data.data.components.length === 0) &&
                                      this.currentMenuType === 'cohort' &&
                                      this.singleSchoolData;
        
        if (needsFallbackComponents) {
            console.log('Server returned empty components array, generating fallback student components');
            
            // Create fallback components for students in a cohort
            const cohortSlug = this.singleSchoolData.slug || this.singleSchoolData.name.toLowerCase().replace(/\s+/g, '-');
            const baseUrl = `${window.location.origin}/collections/${cohortSlug}/`;
            
            // Use cohort ID to create business URL path
            const cohortId = this.singleSchoolData.id;
            const businessUrl = `${baseUrl}subgroups/`; // Fallback if can't find business
            
            const fallbackComponents = [
                { label: 'My Business', url: businessUrl },
                { label: 'Cohort', url: baseUrl },
                { label: 'Materials', url: `${baseUrl}courses/` },
                { label: 'Check-in', url: `${baseUrl}assessment/` }
            ];
            
            // Use the fallback components
            this.groupPanel.innerHTML = '';
            const currentUrl = window.location.pathname.replace(/\/$/, ''); // Remove trailing slash
            
            fallbackComponents.forEach(comp => {
                const wrap = document.createElement('div');
                wrap.className = 'group-component-nav-wrap';
                const a = document.createElement('a');
                a.className = 'group-component-nav';
                a.href = comp.url;
                a.textContent = comp.label;

                // Highlight if current
                const compUrl = (new URL(comp.url, window.location.origin)).pathname.replace(/\/$/, '');
                if (compUrl === currentUrl) {
                    a.classList.add('active');
                }

                a.addEventListener('click', () => {
                    this.saveState(this.currentMenuType, this.select ? this.select.value : '', comp.url);
                });
                wrap.appendChild(a);
                this.groupPanel.appendChild(wrap);
            });
            
            this.groupPanel.style.display = 'block';
            return;
        }
        
        // Original component rendering logic
        if (data.success && Array.isArray(data.data.components) && data.data.components.length > 0) {
            this.groupPanel.innerHTML = '';
            const currentUrl = window.location.pathname.replace(/\/$/, ''); // Remove trailing slash
            data.data.components.forEach(comp => {
                const wrap = document.createElement('div');
                wrap.className = 'group-component-nav-wrap';
                const a = document.createElement('a');
                a.className = 'group-component-nav';
                a.href = comp.url;
                a.textContent = comp.label;

                // Highlight if current
                const compUrl = (new URL(comp.url, window.location.origin)).pathname.replace(/\/$/, '');
                if (compUrl === currentUrl) {
                    a.classList.add('active');
                }

                a.addEventListener('click', () => {
                    this.saveState(this.currentMenuType, this.select ? this.select.value : '', comp.url);
                });
                wrap.appendChild(a);
                this.groupPanel.appendChild(wrap);
            });
            this.groupPanel.style.display = 'block';
        } else {
            // Better styling for "No components available" message
            this.groupPanel.innerHTML = '<div class="no-components-message" style="padding:15px; color:#fff; background-color:rgba(0,0,0,0.2); border-radius:4px; margin:10px 22px; text-align:center;">No components available</div>';
        }
    }

    /**
     * Initialize event listeners and open the first dropdown on page load.
     */
    init() {
        // Apply component styles
        this.applyComponentStyles();
        
        // Set a friendly empty state message for the components panel
        const componentsPanel = document.querySelector('.group-components-panel');
        // if (componentsPanel) {
        //     componentsPanel.innerHTML = '<div class="select-group-message" style="text-align:center;color:#fff;padding:30px 15px;"><p style="margin:0;font-weight:500;">Select a group to view components</p></div>';
        // }
        
        // Check user access first
        this.checkUserAccess();

        const buttons = document.querySelectorAll('.sidebar-main-navs .sidebar-main-link');

        buttons.forEach(button => {
            button.addEventListener('click', function () {
                buttons.forEach(btn => {
                    btn.classList.remove('active');
                });

                this.classList.add('active');
                
                // Only set text content if NOT an empty circle
                if (!this.classList.contains('empty-circle')) {
                    this.textContent = this.dataset.menu.charAt(0).toUpperCase() + this.dataset.menu.slice(1);
                }
            });
        });

        // Set up select change handler first
        if (this.select) {
            this.select.addEventListener('change', async () => {
                const groupId = this.select.value;
                if (groupId && this.currentMenuType) {
                    await this.loadComponents(this.currentMenuType, groupId);
                } else {
                    if (this.groupPanel) {
                        this.groupPanel.innerHTML = ''; // Just clear content, don't hide
                    }
                }
            });
        }

        document.querySelectorAll('.sidebar-main-link').forEach(link => {
            link.addEventListener('click', e => {
                // Skip if link is disabled
                if (link.style.pointerEvents === 'none') {
                    e.preventDefault();
                    return;
                }

                e.preventDefault();
                this.closeAllDropdowns();
                const menuKey = link.getAttribute('data-menu');
                const submenu = link.parentElement.querySelector('.sidebar-sub-menu[data-menu="' + menuKey + '"]');
                if (submenu.style.display === 'block') {
                    submenu.style.display = 'none';
                    if (this.groupPanel) {
                        this.groupPanel.innerHTML = ''; // Just clear content, don't hide
                    }
                } else {
                    submenu.style.display = 'block';
                    this.currentMenuType = menuKey;
                    // Clear the select value when switching menu types
                    if (this.select) {
                        this.select.value = '';
                    }
                    this.fetchAndRenderGroups(menuKey);
                    // Clear components panel when switching menu types
                    if (this.groupPanel) {
                        this.groupPanel.innerHTML = ''; // Just clear content, don't hide
                    }
                }
            });
        });

        document.addEventListener('click', e => {
            if (!e.target.closest('.sidebar-nav-group')) {
                this.closeAllDropdowns();
            }
        });

        // Restore saved state on page load
        const savedState = this.getSavedState();
        if (savedState.menuType) {
            const menuLink = document.querySelector(`.sidebar-main-link[data-menu="${savedState.menuType}"]`);
            if (menuLink) {
                const submenu = menuLink.parentElement.querySelector('.sidebar-sub-menu[data-menu="' + savedState.menuType + '"]');
                if (submenu) {
                    submenu.style.display = 'block';
                    this.currentMenuType = savedState.menuType;
                    this.fetchAndRenderGroups(savedState.menuType, savedState.groupId);
                }
            }
        } else {
            // Open the first dropdown if no saved state
            const firstLink = document.querySelector('.sidebar-main-link');
            if (firstLink) {
                const menuKey = firstLink.getAttribute('data-menu');
                const submenu = firstLink.parentElement.querySelector('.sidebar-sub-menu[data-menu="' + menuKey + '"]');
                if (submenu) {
                    submenu.style.display = 'block';
                    this.currentMenuType = menuKey;
                    this.fetchAndRenderGroups(menuKey);
                }
            }
        }
    }

    /**
     * Close all sidebar dropdowns.
     */
    closeAllDropdowns() {
        document.querySelectorAll('.sidebar-sub-menu').forEach(menu => {
            menu.style.display = 'none';
        });
    }

    /**
     * Create components container for users with exactly one group
     * @param {Object} groupData - The single group data 
     */
    createSingleGroupComponents(groupData) {
        if (!groupData) return;
        
        // Find the existing group components panel
        const componentsPanel = document.querySelector('.group-components-panel');
        
        if (!componentsPanel) {
            console.log('Group components panel not found, cannot create single group components');
            return;
        }
        
        console.log('Creating single group components for:', groupData.name);
        
        // Store reference to the panel
        this.singleGroupPanel = componentsPanel;
        
        // Show the panel
        componentsPanel.style.display = 'block';
        
        // Reset any previous content
        componentsPanel.innerHTML = '';
        
        // Add loading indicator
        componentsPanel.innerHTML = '<div class="components-placeholder">Loading components...</div>';
        
        // Load components for this group
        this.loadSingleGroupComponents(groupData);
    }

    /**
     * Load components for the single group
     * @param {Object} groupData - The single group data
     */
    async loadSingleGroupComponents(groupData) {
        if (!groupData || !this.singleGroupPanel) return;
        
        console.log('Loading components for single group:', groupData.name);
        
        try {
            const data = await this.getCachedData('components', groupData.id, async () => {
                const params = new URLSearchParams({
                    action: 'get_group_components',
                    group_id: groupData.id,
                    nonce: this.nonce
                });
                console.log(`Fetching components for single group ${groupData.id} with URL:`, this.ajaxUrl + '?' + params.toString());
                const response = await fetch(this.ajaxUrl + '?' + params.toString());
                return response.json();
            });

            console.log('Single group components data:', data);
            this.renderSingleGroupComponents(data);
        } catch (error) {
            console.error('Error loading single group components:', error);
            this.singleGroupPanel.innerHTML = '<div style="padding:15px;text-align:center;color:#c00;">Error loading components.</div>';
        }
    }

    /**
     * Render components for single group
     * @param {Object} data - The components data
     */
    renderSingleGroupComponents(data) {
        if (!this.singleGroupPanel) return;

        // Clear loading indicator
        this.singleGroupPanel.innerHTML = '';

        // Check if we have components in the response
        const hasComponents = data.success &&
                            data.data.components &&
                            typeof data.data.components === 'object';

        if (hasComponents) {
            console.log('Found categorized components for single group');
            const currentUrl = window.location.pathname.replace(/\/$/, ''); // Remove trailing slash

            // Create the components container
            const componentsContainer = document.createElement('div');
            componentsContainer.className = 'bw-sidebar-components-container';
            this.singleGroupPanel.appendChild(componentsContainer);

            // Use a fragment for better performance when adding multiple elements
            const fragment = document.createDocumentFragment();

            // Track the currently expanded section
            let currentlyExpandedSection = null;

            // Iterate through each category
            Object.entries(data.data.components).forEach(([category, components]) => {
                // Create category section
                const categorySection = document.createElement('div');
                categorySection.className = 'bw-sidebar-category-section';

                // Create category header (clickable)
                const categoryHeader = document.createElement('div');
                categoryHeader.className = 'bw-sidebar-category-header';

                // Add category title
                const categoryTitle = document.createElement('h3');
                categoryTitle.className = 'bw-sidebar-category-title';
                categoryTitle.textContent = category;

                // Add dropdown arrow
                const dropdownArrow = document.createElement('span');
                dropdownArrow.className = 'bw-sidebar-category-arrow';
                dropdownArrow.innerHTML = '▼';

                categoryHeader.appendChild(categoryTitle);
                categoryHeader.appendChild(dropdownArrow);
                categorySection.appendChild(categoryHeader);

                // Create the components list container
                const componentsListContainer = document.createElement('div');
                componentsListContainer.className = 'bw-sidebar-components-list-container';
                componentsListContainer.style.display = 'none'; // Hide initially

                // Create the components list
                const componentsList = document.createElement('ul');
                componentsList.className = 'bw-sidebar-components-list';
                componentsListContainer.appendChild(componentsList);

                // Render each component
                components.forEach(comp => {
                    const listItem = document.createElement('li');
                    listItem.className = 'bw-sidebar-component-item';

                    const link = document.createElement('a');
                    link.className = 'bw-sidebar-component-link';
                    link.href = comp.url;
                    link.textContent = comp.label;

                    // Highlight if current
                    const compUrl = (new URL(comp.url, window.location.origin)).pathname.replace(/\/$/, '');
                    if (compUrl === currentUrl) {
                        listItem.classList.add('bw-sidebar-component-item-active');
                        link.classList.add('bw-sidebar-component-link-active');
                    }

                    listItem.appendChild(link);
                    componentsList.appendChild(listItem);
                });

                categorySection.appendChild(componentsListContainer);
                fragment.appendChild(categorySection);

                // Add click handler for category header
                categoryHeader.addEventListener('click', () => {
                    const isExpanded = componentsListContainer.classList.contains('bw-sidebar-components-list-container-expanded');
                    const arrow = categoryHeader.querySelector('.bw-sidebar-category-arrow');

                    // If clicking the currently expanded section, collapse it
                    if (isExpanded) {
                        componentsListContainer.style.maxHeight = '0';
                        componentsListContainer.classList.remove('bw-sidebar-components-list-container-expanded');
                        arrow.style.transform = 'rotate(0deg)';
                        categoryHeader.classList.remove('bw-sidebar-category-header-expanded');
                        currentlyExpandedSection = null;
                        
                        // Hide the container after animation
                        setTimeout(() => {
                            componentsListContainer.style.display = 'none';
                        }, 300); // Match the transition duration
                    } else {
                        // If there's a currently expanded section, collapse it first
                        if (currentlyExpandedSection) {
                            const prevContainer = currentlyExpandedSection.querySelector('.bw-sidebar-components-list-container');
                            const prevHeader = currentlyExpandedSection.querySelector('.bw-sidebar-category-header');
                            const prevArrow = prevHeader.querySelector('.bw-sidebar-category-arrow');

                            prevContainer.style.maxHeight = '0';
                            prevContainer.classList.remove('bw-sidebar-components-list-container-expanded');
                            prevArrow.style.transform = 'rotate(0deg)';
                            prevHeader.classList.remove('bw-sidebar-category-header-expanded');
                            
                            // Hide the previous container after animation
                            setTimeout(() => {
                                prevContainer.style.display = 'none';
                            }, 300); // Match the transition duration
                        }

                        // Show and expand the clicked section
                        componentsListContainer.style.display = 'block';
                        requestAnimationFrame(() => {
                            componentsListContainer.classList.add('bw-sidebar-components-list-container-expanded');
                            const scrollHeight = componentsListContainer.scrollHeight;
                            componentsListContainer.style.maxHeight = scrollHeight + 'px';
                        });
                        arrow.style.transform = 'rotate(180deg)';
                        categoryHeader.classList.add('bw-sidebar-category-header-expanded');
                        currentlyExpandedSection = categorySection;
                    }
                });

                // Check if this category contains the current page for initial expansion
                const shouldExpandInitially = components.some(comp => {
                    const compUrl = (new URL(comp.url, window.location.origin)).pathname.replace(/\/$/, '');
                    return compUrl === currentUrl;
                });

                if (shouldExpandInitially) {
                    categorySection.classList.add('bw-sidebar-category-section-expand-on-load');
                }
            });

            // Append all categories
            componentsContainer.appendChild(fragment);

            // Handle initial expansion - only expand the section containing the current page
            requestAnimationFrame(() => {
                const expandOnLoadSection = componentsContainer.querySelector('.bw-sidebar-category-section-expand-on-load');
                if (expandOnLoadSection) {
                    const componentsListContainer = expandOnLoadSection.querySelector('.bw-sidebar-components-list-container');
                    const categoryHeader = expandOnLoadSection.querySelector('.bw-sidebar-category-header');
                    const arrow = categoryHeader.querySelector('.bw-sidebar-category-arrow');

                    // Show and expand the section
                    componentsListContainer.style.display = 'block';
                    componentsListContainer.style.transition = 'none';
                    componentsListContainer.classList.add('bw-sidebar-components-list-container-expanded');
                    const scrollHeight = componentsListContainer.scrollHeight;
                    componentsListContainer.style.maxHeight = scrollHeight + 'px';
                    arrow.style.transform = 'rotate(180deg)';
                    categoryHeader.classList.add('bw-sidebar-category-header-expanded');
                    currentlyExpandedSection = expandOnLoadSection;

                    setTimeout(() => {
                        componentsListContainer.style.transition = 'max-height 0.3s ease-in-out, padding 0.3s ease-in-out';
                    }, 50);

                    expandOnLoadSection.classList.remove('bw-sidebar-category-section-expand-on-load');
                }
            });

        } else {
            // No components found
            const noComponentsMsg = document.createElement('div');
            noComponentsMsg.className = 'bw-no-components-message';
            noComponentsMsg.textContent = 'No components available';

            this.singleGroupPanel.appendChild(noComponentsMsg);
        }
    }

    /**
     * Apply CSS styling for components
     */
    applyComponentStyles() {
        // Create a style element if it doesn't exist
        let styleElement = document.getElementById('sidebar-dropdown-styles');
        if (!styleElement) {
            styleElement = document.createElement('style');
            styleElement.id = 'sidebar-dropdown-styles';
            document.head.appendChild(styleElement);
        }
        
        // Add styles
        styleElement.textContent = `
            .bw-sidebar-single-group-container {
                margin-top: 20px;
            }
            
            .bw-sidebar-group-title {
                color: #ffffff;
                margin: 0 0 10px 0;
                padding: 0 5px;
                font-size: 18px;
                font-weight: 500;
            }
            
            .bw-sidebar-components-container, .bw-group-components-panel {
                min-height: 300px;
                display: block !important;
                width: 100%;
                background: #0E0F53;
                border-radius: 8px;
                padding: 15px;
                box-shadow: 0 2px 8px rgba(0,0,0,0.08);
                visibility: visible !important;
                opacity: 1 !important;
                z-index: 5;
                transition: all 0.3s ease;
            }
            
            .bw-sidebar-components-container.loading, .bw-group-components-panel.loading {
                opacity: 0.7 !important;
            }
            
            .bw-components-placeholder {
                padding: 15px;
                color: #ffffff;
                // text-align: center;
                font-size: 14px;
                opacity: 0.7;
            }
            
            .bw-sidebar-components-list {
                margin: 0;
                padding: 0;
                list-style: none;
            }
            
            .bw-sidebar-component-item {
                padding: 8px 0;
                border-bottom: 1px solid rgba(255,255,255,0.1);
                transition: background-color 0.2s ease;
            }
            
            .bw-sidebar-component-item:last-child {
                border-bottom: none;
            }
            
            .bw-sidebar-component-item:hover {
                background-color: rgba(255,255,255,0.05);
            }
            
            .bw-sidebar-component-item-active {
                background-color: rgba(255,255,255,0.1);
            }
            
            .bw-sidebar-component-link {
                color: #ffffff;
                text-align: left !important;
                text-decoration: none;
                display: block;
                font-size: 14px;
                font-weight: 500;
            }
            
            .bw-sidebar-component-link-active {
                font-weight: 700;
            }
            
            .bw-no-components-message {
                padding: 15px;
                color: #ffffff;
                background-color: rgba(0,0,0,0.2);
                border-radius: 6px;
                margin: 10px 15px;
                // text-align: center;
                font-size: 14px;
                font-weight: 500;
            }
            
            .bw-sidebar-category-section {
                margin-bottom: 10px;
                border-radius: 6px;
                overflow: hidden;
            }

            .bw-sidebar-category-header {
                display: flex;
                // align-items: center;
                padding: 12px 15px;
                background-color: rgba(255,255,255,0.05);
                border-radius: 6px;
                cursor: pointer;
                transition: background-color 0.2s ease;
            }
            
            .bw-sidebar-category-header:hover {
                background-color: rgba(255,255,255,0.1);
            }
            
            .bw-sidebar-category-header-expanded {
                background-color: rgba(255,255,255,0.1);
            }
            
            .bw-sidebar-category-title {
                color: white !important;
                font-size: 15px !important;
                font-weight: 600 !important;
                margin: 0;
                flex: 1;
            }
            
            .bw-sidebar-category-arrow {
                color: #ffffff;
                font-size: 12px;
                transition: transform 0.3s ease;
                margin-left: 10px;
            }
            
            .bw-sidebar-components-list-container {
                max-height: 0;
                overflow: hidden;
                transition: max-height 0.3s ease-in-out, padding 0.3s ease-in-out;
                padding: 0 15px;
                opacity: 0;
                transform: translateY(-10px);
                transition: max-height 0.3s ease-in-out, 
                           padding 0.3s ease-in-out,
                           opacity 0.3s ease-in-out,
                           transform 0.3s ease-in-out;
            }
            
            .bw-sidebar-components-list-container-expanded {
                max-height: 1000px;
                padding-top: 10px;
                padding-bottom: 10px;
                overflow: hidden;
                opacity: 1;
                transform: translateY(0);
            }
        `;
    }
}

// Initialize the sidebar dropdown when DOM is ready

document.addEventListener('DOMContentLoaded', function() {
    // Check if we're on the checkout page
    if (window.location.href.includes('/checkout/')) {
        // Refresh the page after a short delay
        setTimeout(function() {
            window.location.reload();
        }, 1000);
    }

    if (typeof collections_ajax !== 'undefined') {
        new SidebarDropdown({
            ajaxUrl: collections_ajax.ajaxurl,
            nonce: collections_ajax.nonce
        });
    }
});

// Add global event listener to clear sidebar cache on Ctrl+F5
window.addEventListener('keydown', function(e) {
    if (e.key === 'F5' && e.ctrlKey) {
        // Remove all sidebarCache_* keys
        Object.keys(localStorage).forEach(function(k) {
            if (k.startsWith('sidebarCache_')) {
                localStorage.removeItem(k);
            }
        });
    }
});

// Clear cache on logout
document.addEventListener('click', function(e) {
    // Check if the clicked element is a logout link
    if (e.target.closest('a[href*="wp-login.php?action=logout"]')) {
        // Clear all sidebarCache_* keys
        Object.keys(localStorage).forEach(function(k) {
            if (k.startsWith('sidebarCache_')) {
                localStorage.removeItem(k);
            }
        });
    }
});

// Add event listener for user switch or role change
if (typeof wp !== 'undefined' && wp.customize) {
    wp.customize('user_id', function(setting) {
        setting.bind(function() {
            // Clear all sidebarCache_* keys
            Object.keys(localStorage).forEach(function(k) {
                if (k.startsWith('sidebarCache_')) {
                    localStorage.removeItem(k);
                }
            });
        });
    });

    wp.customize('user_role', function(setting) {
        setting.bind(function() {
            // Clear all sidebarCache_* keys
            Object.keys(localStorage).forEach(function(k) {
                if (k.startsWith('sidebarCache_')) {
                    localStorage.removeItem(k);
                }
            });
        });
    });
}

// Add CSS for new UI elements
const style = document.createElement('style');
style.textContent = `
    .sidebar-main-link .circle {
        background: none;
        border: 2px solid #ccc;
    }
    .sidebar-main-link .circle.empty-circle {
        background: none;
        border: 2px solid #ccc;
    }
    .sidebar-main-link.active .circle {
        background: none;
        border: 2px solid #ccc;
    }
    .single-school-name {
        font-size: 1.8em;
        color: white !important;
        font-weight: 500;
    }
    .group-components-panel {
        margin-top: 10px;
    }
    /* Make empty circles smaller for non-admin users */
    body:not(.user-is-admin):not(.administrator) .sidebar-main-link .circle.empty-circle {
        width: 8px;
        height: 8px;
        margin: 0 2px;
    }
    /* Make button smaller when user has only one school */
    .sidebar-main-link.single-school-button {
        width: 50px;
        height: 50px;
    }
`;
document.head.appendChild(style);
