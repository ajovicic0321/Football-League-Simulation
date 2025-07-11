<template>
    <div class="container">
        <div class="card">
            <div class="card-header">
                <h3>Football League Simulation</h3>
            </div>
            <div class="card-body">
                <p>Vue.js 2 is working! 🎉</p>
                <p>Current count: {{ count }}</p>
                <button @click="increment" class="btn btn-primary">
                    Increment
                </button>
            </div>
        </div>
    </div>
</template>

<script>
export default {
    name: 'ExampleComponent',
    data() {
        return {
            count: 0
        }
    },
    methods: {
        increment() {
            this.count++;
        }
    },
    mounted() {
        console.log('Vue.js 2 component mounted successfully!');
    }
}
</script>

<style scoped>
.container {
    max-width: 600px;
    margin: 2rem auto;
}

.card {
    border: 1px solid #ddd;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.card-header {
    background-color: #f8f9fa;
    padding: 1rem;
    border-bottom: 1px solid #ddd;
    border-radius: 8px 8px 0 0;
}

.card-header h3 {
    margin: 0;
    color: #333;
}

.card-body {
    padding: 1.5rem;
}

.btn {
    background-color: #007bff;
    color: white;
    border: none;
    padding: 0.5rem 1rem;
    border-radius: 4px;
    cursor: pointer;
    transition: background-color 0.2s;
}

.btn:hover {
    background-color: #0056b3;
}
</style> 