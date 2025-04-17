class TypeWriter {
    constructor(options) {
        this.element = options.element;
        this.texts = Array.isArray(options.text) ? options.text : [options.text];
        this.currentTextIndex = 0;
        this.speed = options.speed || 100;
        this.cursor = options.cursor || '|';
        this.cursorSpeed = options.cursorSpeed || 400;
        this.delay = options.delay || 0;
        this.deleteSpeed = options.deleteSpeed || 50;
        this.deleteDelay = options.deleteDelay || 2000;
        this.loop = options.loop !== undefined ? options.loop : true;
        
        this.currentChar = 0;
        this.cursorElement = null;
        this.isRunning = false;
        this.isDeleting = false;
    }

    start() {
        if (this.isRunning) return;
        this.isRunning = true;
        
        // Create cursor element
        this.cursorElement = document.createElement('span');
        this.cursorElement.textContent = this.cursor;
        this.cursorElement.style.animation = `cursorBlink ${this.cursorSpeed}ms infinite`;
        this.element.appendChild(this.cursorElement);

        // Add CSS for cursor animation
        if (!document.querySelector('#typewriter-styles')) {
            const style = document.createElement('style');
            style.id = 'typewriter-styles';
            style.textContent = `
                @keyframes cursorBlink {
                    0%, 100% { opacity: 1; }
                    50% { opacity: 0; }
                }
            `;
            document.head.appendChild(style);
        }

        // Start typing after delay
        setTimeout(() => this.type(), this.delay);
    }

    getCurrentText() {
        return this.texts[this.currentTextIndex];
    }

    type() {
        const currentText = this.getCurrentText();
        
        if (!this.isDeleting && this.currentChar < currentText.length) {
            // Typing
            this.element.insertBefore(
                document.createTextNode(currentText[this.currentChar]),
                this.cursorElement
            );
            this.currentChar++;
            setTimeout(() => this.type(), this.speed);
        } else if (!this.isDeleting && this.currentChar >= currentText.length) {
            // Finished typing current text
            if (this.loop) {
                this.isDeleting = true;
                setTimeout(() => this.type(), this.deleteDelay);
            } else {
                this.isRunning = false;
            }
        } else if (this.isDeleting && this.currentChar > 0) {
            // Deleting
            this.currentChar--;
            this.element.textContent = currentText.substring(0, this.currentChar);
            this.element.appendChild(this.cursorElement);
            setTimeout(() => this.type(), this.deleteSpeed);
        } else if (this.isDeleting && this.currentChar === 0) {
            // Finished deleting, move to next text
            this.isDeleting = false;
            this.currentTextIndex = (this.currentTextIndex + 1) % this.texts.length;
            setTimeout(() => this.type(), this.delay);
        }
    }

    reset() {
        this.currentChar = 0;
        this.currentTextIndex = 0;
        this.isRunning = false;
        this.isDeleting = false;
        this.element.textContent = '';
        if (this.cursorElement) {
            this.cursorElement.remove();
            this.cursorElement = null;
        }
    }
}

// Initialize typewriter when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    const typewriter = new TypeWriter({
        element: document.querySelector('.typewriter'),
        text: [
            'Store Your Memories',
            'Secure Your Media',
            'Access Anywhere'
        ],
        speed: 100,          // Typing speed
        deleteSpeed: 50,     // Deleting speed
        cursor: '|',
        cursorSpeed: 400,
        delay: 500,          // Initial delay
        deleteDelay: 2000,   // How long to wait before deleting
        loop: true          // Enable looping
    });
    
    typewriter.start();
});