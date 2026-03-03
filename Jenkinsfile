pipeline {
    agent any

    environment {
        APP_NAME = "the-tip-top-api"
        DOCKER_IMAGE = "joanisky/the-tip-top-api"
        DOCKER_TAG = "${BUILD_NUMBER}"
        KUBE_NAMESPACE = "the-tip-top-api"
        KUBE_DEPLOYMENT = "symfony-api"
    }

    stages {

        stage('Checkout') {
            steps {
                checkout scm
                script {
                    echo "Build: ${BUILD_NUMBER}"
                }
            }
        }

        stage('Build Docker Image') {
            steps {
                withCredentials([
                    usernamePassword(credentialsId: 'jenkins-dockerhub', usernameVariable: 'DOCKER_USER', passwordVariable: 'DOCKER_PASS')
                ]) {
                    sh '''
                        docker login -u $DOCKER_USER -p $DOCKER_PASS

                        docker build \
                            -f .docker/Dockerfile \
                            --target prod \
                            --build-arg APP_ENV=prod \
                            --build-arg APP_SECRET=dummysecret \
                            -t ${DOCKER_IMAGE}:${DOCKER_TAG} .

                        docker push ${DOCKER_IMAGE}:${DOCKER_TAG}
                    '''
                }
            }
        }
        stage('Deploy JWT Secrets') {
            when { expression { env.GIT_BRANCH == 'origin/main' } }
            steps {
                script {
                    withCredentials([
                        file(credentialsId: 'kubeconfig',      variable: 'KUBECONFIG_FILE'),
                        file(credentialsId: 'jwt-private-key', variable: 'JWT_PRIVATE_FILE'),
                        file(credentialsId: 'jwt-public-key',  variable: 'JWT_PUBLIC_FILE')
                    ]) {
                        sh '''
                            export KUBECONFIG=$KUBECONFIG_PATH

                            echo "Ensuring JWT secrets are up to date..."
                            kubectl delete secret jwt-keys -n ${KUBE_NAMESPACE} || true
                            kubectl create secret generic jwt-keys \
                                --from-file=private.pem=$JWT_PRIVATE_FILE \
                                --from-file=public.pem=$JWT_PUBLIC_FILE \
                                -n ${KUBE_NAMESPACE}
                        '''
                    }
                }
            }
        }
        stage('Deploy OAuth Secrets') {
            when { expression { env.GIT_BRANCH == 'origin/main' } }
            steps {
                script {
                    withCredentials([
                        file(credentialsId: 'kubeconfig', variable: 'KUBECONFIG_FILE'),
                        string(credentialsId: 'GOOGLE_CLIENT_ID', variable: 'GOOGLE_ID'),
                        string(credentialsId: 'GOOGLE_CLIENT_SECRET', variable: 'GOOGLE_SECRET'),
                        string(credentialsId: 'FACEBOOK_CLIENT_ID', variable: 'FACEBOOK_ID'),
                        string(credentialsId: 'FACEBOOK_CLIENT_SECRET', variable: 'FACEBOOK_SECRET')
                    ]) {
                        sh '''
                            export KUBECONFIG=$KUBECONFIG_FILE

                            echo "Deploying OAuth secrets..."
                            kubectl create secret generic oauth-secrets \
                                --from-literal=GOOGLE_CLIENT_ID=${GOOGLE_ID} \
                                --from-literal=GOOGLE_CLIENT_SECRET=${GOOGLE_SECRET} \
                                --from-literal=FACEBOOK_CLIENT_ID=${FACEBOOK_ID} \
                                --from-literal=FACEBOOK_CLIENT_SECRET=${FACEBOOK_SECRET} \
                                --from-literal=TRUSTED_PROXIES='10.0.0.0/8,172.16.0.0/12,192.168.0.0/16' \
                                -n ${KUBE_NAMESPACE} \
                                --dry-run=client -o yaml | kubectl apply -f -
                        '''
                    }
                }
            }
        }
        stage('Deploy Monitoring Stack') {
            when { expression { env.GIT_BRANCH == 'origin/main' } }
            steps {
                script {
                    withCredentials([
                        file(credentialsId: 'kubeconfig', variable: 'KUBECONFIG_FILE'),
                        string(credentialsId: 'GRAFANA_ADMIN_PASSWORD', variable: 'GRAFANA_PASS'),
                        string(credentialsId: 'GRAFANA_ADMIN_USER', variable: 'GRAFANA_USER')
                    ]) {
                        sh """
                                export KUBECONFIG=\$KUBECONFIG_FILE

                                # Manifests Kubernetes
                                kubectl apply -f k8s/monitoring/

                                # Secret Grafana
                                kubectl create secret generic grafana-admin-secret \
                                   --from-literal=admin-user=\${GRAFANA_USER} \
                                   --from-literal=admin-password=\${GRAFANA_PASS} \
                                   --namespace monitoring \
                                   --dry-run=client -o yaml | kubectl apply -f -

                                # Helm (installe prometheus)
                                helm repo add prometheus-community https://prometheus-community.github.io/helm-charts
                                helm repo update

                                helm upgrade --install kube-prometheus-stack \
                                   prometheus-community/kube-prometheus-stack \
                                   --namespace monitoring \
                                   --values k8s/monitoring/helm-values.yaml \
                                   --set grafana.adminPassword=\${GRAFANA_PASS} \
                                   --wait \
                                   --timeout 5m

                                kubectl apply -f k8s/monitoring/ingress-grafana.yaml
                            """
                        }
                }
            }
        }
        stage('Deploy to Kubernetes') {
            when {
                expression { env.GIT_BRANCH == 'origin/main' }
            }
            steps {
                script {
                    withCredentials([
                        file(credentialsId: 'kubeconfig', variable: 'KUBECONFIG_FILE')
                    ]) {
                        sh '''
                            export KUBECONFIG=$KUBECONFIG_FILE

                            echo "Application des manifests Kubernetes..."
                            kubectl apply -k k8s/ -n ${KUBE_NAMESPACE}

                            echo "Mise à jour de l'image vers: ${DOCKER_TAG}"
                            kubectl set image deployment/${KUBE_DEPLOYMENT} \
                                app=${DOCKER_IMAGE}:${DOCKER_TAG} \
                                -n ${KUBE_NAMESPACE}

                            echo "Attente du rollout..."
                            kubectl rollout status deployment/${KUBE_DEPLOYMENT} \
                                -n ${KUBE_NAMESPACE} \
                                --timeout=300s
                        '''
                    }
                }
            }
        }
        stage('Database Migrations') {
            when {
                expression { env.GIT_BRANCH == 'origin/main' }
            }
            steps {
                script {
                    withCredentials([
                        file(credentialsId: 'kubeconfig', variable: 'KUBECONFIG_FILE')
                    ]) {
                        sh '''
                            export KUBECONFIG=$KUBECONFIG_PATH

                            echo "Mise à jour de la base de données ..."

                            # Crée la base si elle n'existe pas
                            kubectl exec deployment/${KUBE_DEPLOYMENT} -n ${KUBE_NAMESPACE} -- \
                                php bin/console doctrine:database:create --if-not-exists --env=prod || true

                            kubectl exec deployment/${KUBE_DEPLOYMENT} -n ${KUBE_NAMESPACE} -- \
                                php bin/console doctrine:schema:update --force --env=prod || true

                            # Forcer la suppression du cache avant cache:clear
                            kubectl exec deployment/${KUBE_DEPLOYMENT} -n ${KUBE_NAMESPACE} -- \
                                rm -rf /var/www/html/var/cache/prod || true

                            # Cache clear
                            kubectl exec deployment/${KUBE_DEPLOYMENT} -n ${KUBE_NAMESPACE} -- \
                                php bin/console cache:clear --env=prod

                            echo "Redémarrage de phpMyAdmin"
                            kubectl rollout restart deployment/phpmyadmin -n ${KUBE_NAMESPACE} || true
                        '''
                    }
                }
            }
        }
        stage('Verify Deployment') {
            when {
                expression { env.GIT_BRANCH == 'origin/main' }
            }
            steps {
                withCredentials([
                    file(credentialsId: 'kubeconfig', variable: 'KUBECONFIG_FILE')
                ]) {
                    sh '''
                        export KUBECONFIG=$KUBECONFIG_FILE

                        echo "État du déploiement :"
                        echo ""

                        echo "=== Pods ==="
                        kubectl get pods -n ${KUBE_NAMESPACE}
                        echo ""

                        echo "=== Services ==="
                        kubectl get svc -n ${KUBE_NAMESPACE}
                        echo ""

                        echo "=== Ingress ==="
                        kubectl get ingress -n ${KUBE_NAMESPACE}
                        echo ""

                        echo "=== Secrets ==="
                        kubectl get secrets -n ${KUBE_NAMESPACE}
                        echo ""

                        echo "=== ConfigMaps ==="
                        kubectl get configmaps -n ${KUBE_NAMESPACE}
                    '''
                }
            }
        }
        stage('Health Check') {
            steps {
                script {
                    timeout(time: 2, unit: 'MINUTES') {
                        waitUntil {
                            script {
                                def response = sh(
                                    // Teste la route /api (doc Swagger)
                                    script: 'curl -s -o /dev/null -w "%{http_code}" https://api.the-tip-top.jonathanlore.fr/api',
                                    returnStdout: true
                                ).trim()

                                if (response == '200') {
                                    echo "✅ API accessible"
                                    return true  // Stop le wait
                                } else {
                                    echo "⏳ En attente..."
                                    sleep 5
                                    return false  // Continue le wait
                                }
                            }
                        }
                    }
                }
            }
        }
    }

    post {
        always {
            // Supprime les images locales pour ne pas saturer le disque du VPS
            sh "docker rmi ${DOCKER_IMAGE}:${DOCKER_TAG} || true"
            cleanWs()
        }
        success {
            echo "✅ ${APP_NAME} déployé avec succès !"
            echo "🌐 API : https://api.the-tip-top.jonathanlore.fr/api"
        }
        failure {
            echo "❌ Pipeline échoué"
        }
    }
}
