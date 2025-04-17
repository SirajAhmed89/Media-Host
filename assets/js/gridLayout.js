class GridLayout {
    constructor(options) {
        this.container = options.container;
        this.itemSelector = options.itemSelector;
        this.columns = options.columns || {
            default: 3,
            1024: 2,
            640: 1
        };
        
        this.init();
        this.bindEvents();
    }

    init() {
        this.container.style.position = 'relative';
        this.items = Array.from(this.container.querySelectorAll(this.itemSelector));
        this.layout();
    }

    bindEvents() {
        let resizeTimer;
        window.addEventListener('resize', () => {
            clearTimeout(resizeTimer);
            resizeTimer = setTimeout(() => this.layout(), 100);
        });

        // Handle images loading
        this.items.forEach(item => {
            const images = item.getElementsByTagName('img');
            Array.from(images).forEach(img => {
                if (!img.complete) {
                    img.addEventListener('load', () => this.layout());
                }
            });
        });
    }

    getColumns() {
        const width = window.innerWidth;
        let columns = this.columns.default;

        Object.entries(this.columns)
            .sort(([a], [b]) => parseInt(b) - parseInt(a))
            .forEach(([breakpoint, cols]) => {
                if (width <= parseInt(breakpoint)) {
                    columns = cols;
                }
            });

        return columns;
    }

    layout() {
        const columns = this.getColumns();
        const containerWidth = this.container.offsetWidth;
        const columnWidth = containerWidth / columns;
        const columnHeights = Array(columns).fill(0);
        
        this.items.forEach(item => {
            // Reset item styles
            item.style.position = 'absolute';
            item.style.width = `${columnWidth}px`;
            
            // Find the shortest column
            const minHeight = Math.min(...columnHeights);
            const columnIndex = columnHeights.indexOf(minHeight);
            
            // Position the item
            item.style.transform = `translateX(${columnIndex * columnWidth}px) translateY(${minHeight}px)`;
            
            // Update column height
            columnHeights[columnIndex] += item.offsetHeight + 16; // 16px for margin
        });
        
        // Update container height
        this.container.style.height = `${Math.max(...columnHeights)}px`;
    }

    reloadItems() {
        this.items = Array.from(this.container.querySelectorAll(this.itemSelector));
        this.layout();
    }
} 