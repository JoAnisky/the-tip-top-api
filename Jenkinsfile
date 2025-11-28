pipeline {
    agent any

    environment {
        APP_NAME = "the-tip-top-api"
        DOCKER_IMAGE = "joanisky/the-tip-top-api"
        DOCKER_TAG = "latest"
        DOCKER_TAG_BUILD = "${BUILD_NUMBER}"
        REGISTRY = "index.docker.io"
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
                withCredentials([usernamePassword(credentialsId: 'jenkins-dockerhub', usernameVariable: 'DOCKER_USER', passwordVariable: 'DOCKER_PASS')]) {
                    sh '''
                        docker login -u $DOCKER_USER -p $DOCKER_PASS
                        docker build \
                            -f .docker/Dockerfile \
                            --target prod \
                            --build-arg APP_ENV=prod \
                            --build-arg APP_SECRET=dummysecret \
                            -t ${DOCKER_IMAGE}:${DOCKER_TAG} \
                            -t ${DOCKER_IMAGE}:${DOCKER_TAG_BUILD} .
                        docker push ${DOCKER_IMAGE}:${DOCKER_TAG}
                        docker push ${DOCKER_IMAGE}:${DOCKER_TAG_BUILD}
                    '''
                }
            }
        }

        stage('Deploy JWT Secrets') {
            when {
                expression { env.GIT_BRANCH == 'origin/main' }
            }
            steps {
                script {
                    withCredentials([
                        file(credentialsId: 'kubeconfig', variable: 'KUBECONFIG_FILE'),
                        file(credentialsId: 'jwt-private-key', variable: 'JWT_PRIVATE_FILE'),
                        file(credentialsId: 'jwt-public-key', variable: 'JWT_PUBLIC_FILE')
                    ]) {
                        sh '''
                            mkdir -p ~/.kube
                            cp $KUBECONFIG_FILE ~/.kube/config
                            chmod 600 ~/.kube/config

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

        stage('Deploy to Kubernetes') {
            when {
                expression { env.GIT_BRANCH == 'origin/main' }
            }
            steps {
                script {
                    withCredentials([file(credentialsId: 'kubeconfig', variable: 'KUBECONFIG_FILE')]) {
                        sh '''
                            mkdir -p ~/.kube
                            cp $KUBECONFIG_FILE ~/.kube/config
                            chmod 600 ~/.kube/config

                            echo "** Applying Kubernetes manifests **"
                            kubectl apply -k k8s/

                            echo "** Updating API image **"
                            kubectl set image deployment/${KUBE_DEPLOYMENT} app=${DOCKER_IMAGE}:${DOCKER_TAG} -n ${KUBE_NAMESPACE}

                            echo "** Force pulling latest image **"
                            TIMESTAMP=$(date +%s)
                            kubectl patch deployment ${KUBE_DEPLOYMENT} -n ${KUBE_NAMESPACE} \
                              -p '{"spec":{"template":{"metadata":{"annotations":{"timestamp":"'$TIMESTAMP'"}}}}}'

                            echo "** Waiting for rollout **"
                            kubectl rollout status deployment/${KUBE_DEPLOYMENT} -n ${KUBE_NAMESPACE} --timeout=300s

                            echo "** Restarting phpMyAdmin  **"
                            kubectl rollout restart deployment/phpmyadmin -n ${KUBE_NAMESPACE}

                            echo "** Creating database if not exists **"
                            kubectl exec deployment/${KUBE_DEPLOYMENT} -n ${KUBE_NAMESPACE} -- \
                                php bin/console doctrine:database:create --if-not-exists --env=prod || true

                            echo "** Updating database schema **"
                            kubectl exec deployment/${KUBE_DEPLOYMENT} -n ${KUBE_NAMESPACE} -- \
                                php bin/console doctrine:schema:update --force --env=prod || true

                            echo "Deployment completed!"
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
                script {
                    withCredentials([file(credentialsId: 'kubeconfig', variable: 'KUBECONFIG_FILE')]) {
                        sh '''
                            cp $KUBECONFIG_FILE ~/.kube/config

                            echo "=== Pods ==="
                            kubectl get pods -n ${KUBE_NAMESPACE}

                            echo "=== Services ==="
                            kubectl get svc -n ${KUBE_NAMESPACE}

                            echo "=== Ingress ==="
                            kubectl get ingress -n ${KUBE_NAMESPACE}

                            echo "=== JWT Secrets ==="
                            kubectl get secrets -n ${KUBE_NAMESPACE} | grep jwt
                        '''
                    }
                }
            }
        }
    }

    post {
        always {
            cleanWs()
        }
        success {
            echo "✅ ${APP_NAME} deployed successfully!"
        }
        failure {
            echo "❌ Pipeline failed"
        }
    }
}
